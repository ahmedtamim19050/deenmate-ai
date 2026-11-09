<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\UserResource;
use App\Models\UserPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function update(Request $request): UserResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'country' => ['sometimes', 'nullable', 'string', 'size:2'],
            'onboarded' => ['sometimes', 'boolean'],
        ]);
        $user = $request->user();
        $user->fill(array_intersect_key($data, array_flip(['name', 'country'])));
        if (($data['onboarded'] ?? false) && $user->onboarded_at === null) {
            $user->onboarded_at = now();
        }
        $user->save();

        return new UserResource($user);
    }

    public function preferences(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->presentable(
            UserPreference::query()->firstOrCreate(['user_id' => $request->user()->id])->refresh()
        )]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'locale' => ['sometimes', 'in:en,ar,bn'],
            'theme' => ['sometimes', 'in:system,light,dark'],
            'haptics_enabled' => ['sometimes', 'boolean'],
            'show_points' => ['sometimes', 'boolean'],
            'ai_personalization_enabled' => ['sometimes', 'boolean'],
        ]);
        $preference = UserPreference::query()->firstOrCreate(['user_id' => $request->user()->id]);
        $preference->update($data);

        return response()->json(['data' => $this->presentable($preference->refresh())]);
    }

    private function presentable(UserPreference $p): array
    {
        return $p->only(['locale', 'theme', 'haptics_enabled', 'show_points', 'ai_personalization_enabled']);
    }
}
