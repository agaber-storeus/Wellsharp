<?php

use App\Actions\Users\CreateUserAction;
use App\Models\Role;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

Artisan::command('wellsharp:create-admin', function (CreateUserAction $createUser): int {
    $this->info('Create an Admin account. The password is never displayed or stored in plaintext.');
    $wellsharpId = strtoupper(trim((string) $this->ask('WellSharp ID')));
    $firstName = $this->ask('First name');
    $lastName = $this->ask('Last name');
    $email = trim((string) $this->ask('Email (optional)')) ?: null;
    $password = $this->secret('Password (minimum 12 characters)');
    $confirmation = $this->secret('Confirm password');
    $roleId = Role::where('key', Role::ADMIN)->value('id');

    $validator = Validator::make([
        'wellsharp_id' => $wellsharpId,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'password' => $password,
        'password_confirmation' => $confirmation,
        'role_id' => $roleId,
    ], [
        'wellsharp_id' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:users,wellsharp_id'],
        'first_name' => ['required', 'string', 'max:100'],
        'last_name' => ['required', 'string', 'max:100'],
        'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
        'password' => ['required', 'string', 'min:12', 'confirmed'],
        'role_id' => ['required', 'exists:roles,id'],
    ]);

    if ($validator->fails()) {
        $this->error($validator->errors()->first());

        return 1;
    }

    $user = $createUser->execute([
        'wellsharp_id' => $wellsharpId, 'first_name' => $firstName, 'last_name' => $lastName,
        'email' => $email, 'password' => $password, 'role_id' => $roleId,
    ]);
    $this->info("Admin {$user->wellsharp_id} created.");

    return 0;
})->purpose('Create an Admin account interactively');

Artisan::command('wellsharp:seed-demo', function (): int {
    if (! app()->environment(['local', 'testing'])) {
        $this->error('Demo data is restricted to local and testing environments.');

        return 1;
    }

    $this->call('db:seed', ['--class' => 'Database\\Seeders\\DemoDataSeeder', '--no-interaction' => true]);

    return 0;
})->purpose('Seed repeatable local demo data for implemented WellSharp workflows');

app(Schedule::class)->command('wellsharp:process-exam-schedules')->everyMinute()->withoutOverlapping();
