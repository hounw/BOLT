<?php

namespace App\Console\Commands;

use App\Enums\SystemRole;
use App\Models\User;
use App\Services\AuditLogger;
use Database\Seeders\CoreAccessSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class BootstrapOwnerAdmin extends Command
{
    protected $signature = 'bolt:bootstrap-owner {--confirm-production : Confirm this is an intentional non-local bootstrap}';

    protected $description = 'Interactively create the first non-local owner-admin without exposing credentials in shell history.';

    public function handle(AuditLogger $auditLogger): int
    {
        if (app()->environment('local')) {
            $this->error('Use bolt:create-local-admin in the local environment.');

            return self::FAILURE;
        }

        if (! $this->option('confirm-production')) {
            $this->error('Re-run with --confirm-production after reviewing the production runbook.');

            return self::FAILURE;
        }

        $this->callSilent('db:seed', ['--class' => CoreAccessSeeder::class, '--force' => true]);

        if (User::role(SystemRole::OwnerAdmin->value)->exists()) {
            $this->error('An owner-admin already exists. Manage additional owners through Platform > Access.');

            return self::FAILURE;
        }

        if (! $this->confirm('Create the first owner-admin for this deployment?', false)) {
            $this->warn('Owner bootstrap cancelled.');

            return self::FAILURE;
        }

        $name = $this->ask('Owner name');
        $email = $this->ask('Owner email');
        $password = $this->secret('Temporary password');
        $passwordConfirmation = $this->secret('Confirm temporary password');

        $validator = Validator::make(compact('name', 'email', 'password', 'passwordConfirmation'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'same:passwordConfirmation', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($name, $email, $password): User {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);
            $user->assignRole(SystemRole::OwnerAdmin->value);

            return $user;
        });

        $auditLogger->log('access.initial_owner_created', $user, newValues: [
            'user_id' => $user->id,
            'email' => $user->email,
            'roles' => [SystemRole::OwnerAdmin->value],
        ]);

        $this->info("Initial owner-admin ready: {$user->email}");
        $this->warn('Sign in over HTTPS and replace the temporary password immediately.');

        return self::SUCCESS;
    }
}
