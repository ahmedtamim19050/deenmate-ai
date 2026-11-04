<?php

namespace App\Models;

use App\Models\Concerns\AuditsAdminChanges;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use AuditsAdminChanges, HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_SYSTEM_ADMIN = 'system_admin';

    public const ROLE_CONTENT_EDITOR = 'content_editor';

    public const ROLE_SCHOLAR_REVIEWER = 'scholar_reviewer';

    public const ROLE_SUPPORT_STAFF = 'support_staff';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN => 'Super Administrator',
        self::ROLE_SYSTEM_ADMIN => 'System Administrator',
        self::ROLE_CONTENT_EDITOR => 'Content Editor',
        self::ROLE_SCHOLAR_REVIEWER => 'Scholar Reviewer',
        self::ROLE_SUPPORT_STAFF => 'Support Staff',
    ];

    // admin_role is deliberately absent: it is only assigned by the admin panel.
    protected $fillable = ['name', 'email', 'password', 'country', 'onboarded_at'];

    protected $hidden = ['password', 'remember_token', 'admin_role'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->uuid ??= (string) Str::uuid();
        });
    }

    public function isAdmin(): bool
    {
        return $this->admin_role !== null && array_key_exists($this->admin_role, self::ROLES);
    }

    public function hasAdminRole(string ...$roles): bool
    {
        return in_array($this->admin_role, $roles, true);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin();
    }

    public function preference()
    {
        return $this->hasOne(UserPreference::class);
    }

    public function prayerSetting()
    {
        return $this->hasOne(UserPrayerSetting::class);
    }

    public function prayerTemplates()
    {
        return $this->hasMany(UserPrayerTemplate::class);
    }

    public function notificationPreferences()
    {
        return $this->hasMany(PrayerNotificationPreference::class);
    }

    public function pointBalance()
    {
        return $this->hasOne(UserPointBalance::class);
    }

    public function pointTransactions()
    {
        return $this->hasMany(PointTransaction::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function salahRecords()
    {
        return $this->hasMany(SalahRecord::class);
    }

    public function goals()
    {
        return $this->hasMany(UserGoal::class);
    }

    public function userAchievements()
    {
        return $this->hasMany(UserAchievement::class);
    }

    public function aiConversations()
    {
        return $this->hasMany(AiConversation::class);
    }

    public function devices()
    {
        return $this->hasMany(UserDevice::class);
    }

    public function bookmarks()
    {
        return $this->hasMany(Bookmark::class);
    }
}
