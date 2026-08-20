<?php

namespace Database\Factories;

use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LoginEventFactory extends Factory
{
    protected $model = LoginEvent::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'wellsharp_id' => Str::upper(Str::random(6)),
            'outcome' => 'success',
            'correlation_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Factory Seeder',
            'occurred_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (): array => ['user_id' => null, 'outcome' => 'invalid_credentials']);
    }
}
