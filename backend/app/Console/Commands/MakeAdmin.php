<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Validation\Rule;

/** There are no default admin credentials: each admin is created here. */
class MakeAdmin extends Command
{
    protected $signature = 'deenmate:make-admin {email} {--role=super_admin} {--name=Administrator}';

    protected $description = 'Create an admin panel user, or change the role of an existing user';

    public function handle(): int
    {
        $data = ['email' => $this->argument('email'), 'role' => $this->option('role')];
        $validator = validator($data, ['email' => ['required', 'email'], 'role' => ['required', Rule::in(array_keys(User::ROLES))]]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::INVALID;
        }

        $user = User::query()->where('email', $data['email'])->first();
        if ($user === null) {
            $password = $this->secret('Password (at least 12 characters)');
            if (strlen((string) $password) < 12) {
                $this->error('Password is too short.');

                return self::INVALID;
            }
            $user = User::create(['name' => $this->option('name'), 'email' => $data['email'], 'password' => $password]);
        }

        $user->forceFill(['admin_role' => $data['role'], 'email_verified_at' => $user->email_verified_at ?? now()])->save();
        AuditLog::record('admin.role_assigned', $user, ['role' => $data['role'], 'via' => 'cli']);
        $this->info("{$data['email']} is now {$data['role']}.");

        return self::SUCCESS;
    }
}
