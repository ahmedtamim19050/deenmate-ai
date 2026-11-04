<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\GoalResource;
use App\Models\UserGoal;
use App\Services\Achievements\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GoalController extends Controller
{
    private const RULES = [
        'type' => ['in:quran,dhikr,learning,habit,custom'],
        'title' => ['string', 'max:120'],
        'description' => ['nullable', 'string', 'max:1000'],
        'activity_type_key' => ['nullable', 'exists:activity_types,key'],
        'target_value' => ['integer', 'min:1', 'max:1000000'],
        'period' => ['in:daily,weekly,total'],
        'start_date' => ['nullable', 'date_format:Y-m-d'],
        'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        return GoalResource::collection(
            UserGoal::query()->where('user_id', $request->user()->id)
                ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
                ->orderByDesc('id')->get()
        );
    }

    public function store(Request $request): GoalResource
    {
        $rules = self::RULES;
        foreach (['type', 'title', 'target_value', 'period'] as $required) {
            array_unshift($rules[$required], 'required');
        }
        $goal = UserGoal::create($request->validate($rules) + ['user_id' => $request->user()->id]);

        return new GoalResource($goal->refresh());
    }

    public function update(Request $request, UserGoal $goal, AchievementService $achievements): GoalResource
    {
        $this->authorize('update', $goal);
        $rules = array_map(fn (array $r) => array_merge(['sometimes'], $r), self::RULES);
        $rules['status'] = ['sometimes', 'in:active,paused,completed,archived'];
        $data = $request->validate($rules);

        if (($data['status'] ?? null) === 'completed' && $goal->status !== 'completed') {
            $data['completed_at'] = now();
        }
        $goal->update($data);
        if ($goal->status === 'completed') {
            $achievements->evaluate($request->user());
        }

        return new GoalResource($goal->refresh());
    }

    public function destroy(UserGoal $goal): JsonResponse
    {
        $this->authorize('delete', $goal);
        $goal->delete();

        return response()->json(['message' => 'Goal removed.']);
    }
}
