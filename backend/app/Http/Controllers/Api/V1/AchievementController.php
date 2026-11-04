<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Achievement;
use App\Models\UserAchievement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AchievementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $earned = UserAchievement::query()->where('user_id', $request->user()->id)->pluck('awarded_at', 'achievement_id');

        return response()->json(['data' => Achievement::query()->where('is_active', true)->orderBy('id')->get()->map(fn (Achievement $a) => [
            'key' => $a->key,
            'name' => $a->name,
            'description' => $a->description,
            'icon' => $a->icon,
            'earned' => $earned->has($a->id),
            'awarded_at' => $earned->get($a->id),
        ])]);
    }

    public function mine(Request $request): JsonResponse
    {
        return response()->json(['data' => UserAchievement::query()
            ->with('achievement')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('awarded_at')
            ->get()
            ->map(fn (UserAchievement $u) => [
                'key' => $u->achievement->key,
                'name' => $u->achievement->name,
                'description' => $u->achievement->description,
                'icon' => $u->achievement->icon,
                'awarded_at' => $u->awarded_at?->toIso8601String(),
            ])]);
    }
}
