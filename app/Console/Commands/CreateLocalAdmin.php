<?php

namespace App\Console\Commands;

use App\Enums\SystemRole;
use App\Models\User;
use Database\Seeders\CoreAccessSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateLocalAdmin extends Command
{
    protected $signature = 'bolt:create-local-admin {--email=} {--name=} {--password=}';

    protected $description = 'Create or update a local owner-admin user without storing credentials in the repo.';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('This command only runs in the local environment.');

            return self::FAILURE;
        }

        $email = $this->option('email') ?: $this->ask('Admin email');
        $name = $this->option('name') ?: $this->ask('Admin name', 'Local Admin');
        $password = $this->option('password') ?: $this->secret('Temporary password');

        $validator = Validator::make([
            'email' => $email,
            'name' => $name,
            'password' => $password,
        ], [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->callSilent('db:seed', ['--class' => CoreAccessSeeder::class]);

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $password],
        );

        $user->assignRole(SystemRole::OwnerAdmin->value);

        $this->info("Local owner-admin ready: {$user->email}");
        $this->warn('Do not reuse this temporary password in production.');

        return self::SUCCESS;
    }
}
