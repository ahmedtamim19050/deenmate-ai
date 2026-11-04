<?php

namespace App\Services\Privacy;

use App\Models\AuditLog;
use App\Models\DataExportRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AccountDeletionService
{
    /**
     * Hard delete. Foreign keys cascade through every table holding personal data. Audit rows
     * about this person's own records hold record details, and survive a cascade, so they go too.
     */
    public function delete(User $user): void
    {
        $files = DataExportRequest::query()->where('user_id', $user->id)->whereNotNull('file_path')->pluck('file_path');

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            AuditLog::query()->where('actor_id', $user->id)
                ->where(fn ($q) => $q->where('action', 'like', 'salah.%')->orWhere('action', 'like', 'activity.%')->orWhere('action', 'like', 'auth.%'))
                ->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            // A bare marker: no identity, address or subject is kept.
            AuditLog::query()->create(['action' => 'account.deleted']);
            $user->forceDelete();
        });

        foreach ($files as $file) {
            Storage::disk('local')->delete($file);
        }
    }
}
