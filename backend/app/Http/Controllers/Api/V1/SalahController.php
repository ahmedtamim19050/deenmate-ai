<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\SalahRecordResource;
use App\Models\SalahRecord;
use App\Models\UserPointBalance;
use App\Services\Prayer\PrayerTemplateDefaults;
use App\Services\Salah\SalahService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalahController extends Controller
{
    public function __construct(private SalahService $salah) {}

    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prayer' => ['required', Rule::in(PrayerTemplateDefaults::PRAYERS)],
            'prayer_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:+1 day'],
            'idempotency_key' => ['required', 'uuid'],
            'confirmed' => ['required', 'accepted'],
            'confirmed_at' => ['sometimes', 'date', 'before_or_equal:+1 day'],
            ...$this->itemRules(),
        ]);
        $this->assertTotal($data['items']);

        $result = $this->salah->confirm(
            $request->user(),
            $data['prayer'],
            $data['prayer_date'],
            $data['items'],
            $data['idempotency_key'],
            isset($data['confirmed_at']) ? Carbon::parse($data['confirmed_at']) : null,
        );

        return response()->json([
            'data' => (new SalahRecordResource($result['record']))->resolve(),
            'points_awarded' => $result['points'],
            'duplicate' => $result['duplicate'],
            'balance' => $this->balance($request),
            'new_achievements' => collect($result['achievements'])->map->only(['key', 'name', 'description', 'icon'])->values(),
        ], $result['duplicate'] ? 200 : 201);
    }

    public function update(Request $request, SalahRecord $salah): JsonResponse
    {
        $this->authorize('update', $salah);
        $data = $request->validate($this->itemRules());
        $this->assertTotal($data['items']);
        $result = $this->salah->update($salah, $data['items']);

        return response()->json([
            'data' => (new SalahRecordResource($result['record']))->resolve(),
            'points_awarded' => $result['points'],
            'balance' => $this->balance($request),
        ]);
    }

    public function reverse(Request $request, SalahRecord $salah): JsonResponse
    {
        $this->authorize('update', $salah);

        return response()->json([
            'data' => ['points_reversed' => $this->salah->reverse($salah)],
            'balance' => $this->balance($request),
        ]);
    }

    /** Counts what was recorded and never computes a "missed" figure. */
    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate(['days' => ['sometimes', 'integer', 'min:1', 'max:365']]);
        $days = (int) ($data['days'] ?? 7);
        $from = now()->subDays($days - 1)->toDateString();

        $records = SalahRecord::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->where('prayer_date', '>=', $from)
            ->get();

        return response()->json(['data' => [
            'days' => $days,
            'records' => $records->count(),
            'total_rakats' => (int) $records->sum('total_rakats'),
            'by_prayer' => $records->countBy('prayer'),
        ]]);
    }

    private function itemRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:12'],
            'items.*.template_key' => ['nullable', 'string', 'max:48'],
            'items.*.label' => ['required', 'string', 'max:60'],
            'items.*.category' => ['required', Rule::in(PrayerTemplateDefaults::CATEGORIES)],
            'items.*.rakats' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    private function assertTotal(array $items): void
    {
        if (array_sum(array_column($items, 'rakats')) > 40) {
            throw ValidationException::withMessages(['items' => 'One record can hold at most 40 rakats.']);
        }
    }

    private function balance(Request $request): int
    {
        return (int) (UserPointBalance::query()->whereKey($request->user()->id)->value('balance') ?? 0);
    }
}
