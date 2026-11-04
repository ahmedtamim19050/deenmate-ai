<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Immutable ledger row. A correction is always a new row, never an edit.
 */
class PointTransaction extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_EARN = 'earn';

    public const TYPE_REVERSE = 'reverse';

    public const TYPE_ADMIN = 'admin_adjustment';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Point transactions are immutable.'));
        static::deleting(fn () => throw new LogicException('Point transactions are immutable.'));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function activityLog()
    {
        return $this->belongsTo(ActivityLog::class);
    }

    public function reversal()
    {
        return $this->hasOne(self::class, 'reverses_transaction_id');
    }
}
