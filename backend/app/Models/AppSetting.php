<?php

namespace App\Models;

use App\Models\Concerns\AuditsAdminChanges;
use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    use AuditsAdminChanges;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }

    public static function value(string $key, mixed $default = null): mixed
    {
        return self::query()->where('key', $key)->value('value') ?? $default;
    }
}
