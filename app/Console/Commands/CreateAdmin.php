<?php

namespace App\Console\Commands;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'mtis:create-admin {email} {--name=Administrator} {--username=admin}';

    protected $description = 'Create an administrator with an interactively supplied password';

    public function handle(): int
    {
        $data = ['name' => $this->option('name'), 'username' => $this->option('username'), 'email' => $this->argument('email'), 'password' => $this->secret('Password (12+ characters, mixed case, numbers, symbols)')];
        $validator = Validator::make($data, ['name' => 'required|string|max:120', 'username' => 'required|alpha_dash:ascii|unique:users,username',
            'email' => 'required|email|unique:users,email', 'password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        DB::transaction(function () use ($data) {
            $user = User::create([...$data, 'role' => 'admin', 'active' => true, 'weekly_capacity' => 40, 'work_days' => [1, 2, 3, 4, 5]]);
            AuditEvent::create(['user_id' => $user->id, 'action' => 'user_saved', 'changes' => ['user_id' => $user->id, 'source' => 'console', 'role' => 'admin']]);
        });
        $this->info('Administrator created.');

        return self::SUCCESS;
    }
}
