<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\Prayer\PrayerTemplateDefaults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request, PrayerTemplateDefaults $templates): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'locale' => ['sometimes', 'in:en,ar,bn'],
            'device_name' => ['sometimes', 'string', 'max:80'],
        ]);

        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        UserPreference::create(['user_id' => $user->id, 'locale' => $data['locale'] ?? 'en']);
        $templates->ensureFor($user);

        return response()->json([
            'data' => new UserResource($user),
            'token' => $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:80'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();
        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        return response()->json([
            'data' => new UserResource($user),
            'token' => $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    /** Rotates the token: the new one is issued before the old one is revoked. */
    public function refresh(Request $request): JsonResponse
    {
        $current = $request->user()->currentAccessToken();
        $token = $request->user()->createToken($current->name)->plainTextToken;
        $current->delete();

        return response()->json(['token' => $token]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        PasswordBroker::sendResetLink($request->only('email'));

        // Same reply either way, so this cannot be used to discover accounts.
        return response()->json(['message' => 'If that address has an account, a reset email is on its way.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $status = PasswordBroker::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete();
                AuditLog::record('auth.password_reset', $user, [], $user->id);
            },
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => 'Your password has been reset.']);
    }

    public function registerDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_uuid' => ['required', 'string', 'max:64'],
            'platform' => ['required', 'in:android,ios'],
            'push_token' => ['nullable', 'string', 'max:512'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ]);

        $request->user()->devices()->updateOrCreate(
            ['device_uuid' => $data['device_uuid']],
            $data + ['last_seen_at' => now()],
        );

        return response()->json(['message' => 'Device registered.']);
    }
}
