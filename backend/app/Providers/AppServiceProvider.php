<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\Bookmark;
use App\Models\SalahRecord;
use App\Models\User;
use App\Models\UserGoal;
use App\Policies\OwnedByUserPolicy;
use App\Services\AI\Contracts\LlmProvider;
use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\OpenAiCompatibleProvider;
use App\Services\AI\Providers\UnconfiguredProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LlmProvider::class, fn () => match (config('deenmate.ai.provider')) {
            'anthropic' => new AnthropicProvider,
            'openai_compatible' => new OpenAiCompatibleProvider,
            default => new UnconfiguredProvider,
        });
    }

    public function boot(): void
    {
        TrustProxies::at(config('deenmate.trusted_proxies'));

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        foreach ([ActivityLog::class, SalahRecord::class, UserGoal::class, Bookmark::class] as $model) {
            Gate::policy($model, OwnedByUserPolicy::class);
        }

        // Route model binding finds any row; the policy above decides access.
        Route::model('activity', ActivityLog::class);
        Route::model('goal', UserGoal::class);
        Route::model('salah', SalahRecord::class);

        RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
        RateLimiter::for('public', fn (Request $r) => Limit::perMinute(120)->by($r->ip()));
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(180)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('sync', fn (Request $r) => Limit::perMinute(30)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('ai', fn (Request $r) => Limit::perMinute((int) config('deenmate.ai.requests_per_minute'))->by($r->user()?->id ?: $r->ip()));

        ResetPassword::createUrlUsing(fn (User $user, string $token) => config('deenmate.password_reset_url').'?token='.$token.'&email='.urlencode($user->email));
    }
}
