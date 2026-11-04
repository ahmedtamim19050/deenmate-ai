<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_with_editable_default_templates(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User', 'email' => 'new@example.test',
            'password' => 'long-enough-1', 'password_confirmation' => 'long-enough-1',
        ])->assertCreated()->assertJsonStructure(['data' => ['id', 'email'], 'token']);

        $this->assertNotEmpty($response->json('token'));
        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertSame(12, $user->prayerTemplates()->count());
        $this->assertNull($user->admin_role);
    }

    public function test_register_cannot_grant_an_admin_role(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Attacker', 'email' => 'attacker@example.test',
            'password' => 'long-enough-1', 'password_confirmation' => 'long-enough-1',
            'admin_role' => 'super_admin',
        ])->assertCreated();

        $this->assertNull(User::where('email', 'attacker@example.test')->first()->admin_role);
    }

    public function test_login_and_me_and_logout(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();

        $token = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'correct-password'])
            ->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.email', $user->email)
            ->assertJsonMissingPath('data.password');
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_refresh_rotates_the_token(): void
    {
        $user = User::factory()->create();
        $old = $user->createToken('mobile')->plainTextToken;

        $new = $this->withToken($old)->postJson('/api/v1/auth/refresh')->assertOk()->json('token');

        $this->assertNotSame($old, $new);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_protected_routes_need_a_token(): void
    {
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
        $this->postJson('/api/v1/salah/confirm', [])->assertUnauthorized();
    }

    public function test_forgot_password_does_not_reveal_whether_an_account_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk()->json('message');
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.test'])->assertOk()->json('message');

        $this->assertSame($known, $unknown);
        Notification::assertSentTo($user, ResetPassword::class);
    }
}
