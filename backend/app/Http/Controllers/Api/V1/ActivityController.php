<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ActivityResource;
use App\Models\ActivityLog;
use App\Models\ActivityType;
use App\Services\Activity\ActivityResult;
use App\Services\Activity\ActivityService;
use App\Services\Points\PointsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ActivityController extends Controller
{
    private const ITEM_RULES = [
        'type' => ['required', 'string', 'max:64'],
        'quantity' => ['required', 'integer', 'min:1'],
        'source' => ['required', 'in:in_app,manual'],
        'idempotency_key' => ['required', 'uuid'],
        'occurred_at' => ['required', 'date', 'before_or_equal:+1 day'],
        'local_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:+1 day'],
        'metadata' => ['sometimes', 'array', 'max:12'],
        'confirmed' => ['sometimes', 'boolean'],
    ];

    public function __construct(private ActivityService $activities) {}

    public function types(PointsService $points): JsonResponse
    {
        $types = ActivityType::query()->where('is_active', true)->orderBy('id')->get();

        return response()->json(['data' => $types->map(function (ActivityType $t) use ($points) {
            $rule = $points->activeRule($t->id);

            return $t->only(['key', 'name', 'category', 'unit', 'allows_in_app_source', 'allows_manual_source', 'one_per_day', 'client_creatable', 'max_quantity']) + [
                'points' => $rule?->points ?? 0,
                'per_quantity' => $rule?->per_quantity ?? 1,
            ];
        })]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(self::ITEM_RULES);
        $this->requireConfirmation($data);
        $result = $this->recordItem($request, $data);

        return response()->json($this->present($result), $result->duplicate ? 200 : 201);
    }

    /** Each item stands alone: one bad record never blocks the rest of the queue. */
    public function sync(Request $request): JsonResponse
    {
        $request->validate(['items' => ['required', 'array', 'min:1', 'max:100']]);

        $results = [];
        foreach ($request->input('items') as $item) {
            $key = is_array($item) ? ($item['idempotency_key'] ?? null) : null;
            try {
                $data = validator(is_array($item) ? $item : [], self::ITEM_RULES)->validate();
                $this->requireConfirmation($data);
                $result = $this->recordItem($request, $data);
                $results[] = ['idempotency_key' => $key, 'status' => $result->duplicate ? 'duplicate' : 'created'] + $this->present($result);
            } catch (ValidationException $e) {
                $results[] = ['idempotency_key' => $key, 'status' => 'rejected', 'errors' => $e->errors()];
            }
        }

        return response()->json(['data' => $results]);
    }

    public function history(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'category' => ['sometimes', 'string', 'max:32'],
            'type' => ['sometimes', 'string', 'max:64'],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d'],
        ]);

        $logs = ActivityLog::query()
            ->with('type')
            ->withSum('transactions', 'points')
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->when($data['category'] ?? null, fn ($q, $v) => $q->whereHas('type', fn ($t) => $t->where('category', $v)))
            ->when($data['type'] ?? null, fn ($q, $v) => $q->whereHas('type', fn ($t) => $t->where('key', $v)))
            ->when($data['from'] ?? null, fn ($q, $v) => $q->where('local_date', '>=', $v))
            ->when($data['to'] ?? null, fn ($q, $v) => $q->where('local_date', '<=', $v))
            ->orderByDesc('occurred_at')
            ->paginate(30);

        return ActivityResource::collection($logs);
    }

    public function update(Request $request, ActivityLog $activity): JsonResponse
    {
        $this->authorize('update', $activity);
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.$activity->type->max_quantity],
            'metadata' => ['sometimes', 'array', 'max:12'],
        ]);

        return response()->json($this->present($this->activities->update($activity, $data['quantity'], $data['metadata'] ?? [])));
    }

    public function destroy(ActivityLog $activity): JsonResponse
    {
        $this->authorize('delete', $activity);

        return response()->json(['data' => ['points_reversed' => $this->activities->reverse($activity)]]);
    }

    /** Manual records exist only because the person said so. */
    private function requireConfirmation(array $data): void
    {
        if ($data['source'] === ActivityService::SOURCE_MANUAL && ($data['confirmed'] ?? false) !== true) {
            throw ValidationException::withMessages(['confirmed' => 'Manual records must be confirmed by you.']);
        }
    }

    private function recordItem(Request $request, array $data): ActivityResult
    {
        return $this->activities->record(
            user: $request->user(),
            typeKey: $data['type'],
            quantity: $data['quantity'],
            source: $data['source'],
            idempotencyKey: $data['idempotency_key'],
            occurredAt: Carbon::parse($data['occurred_at']),
            localDate: $data['local_date'],
            metadata: $data['metadata'] ?? [],
        );
    }

    private function present(ActivityResult $r): array
    {
        return [
            'data' => (new ActivityResource($r->log->loadMissing('type')->loadSum('transactions', 'points')))->resolve(),
            'points_awarded' => $r->pointsAwarded,
            'duplicate' => $r->duplicate,
            'balance' => (int) ($r->log->user->pointBalance()->value('balance') ?? 0),
            'new_achievements' => collect($r->newAchievements)->map->only(['key', 'name', 'description', 'icon'])->values(),
        ];
    }
}
