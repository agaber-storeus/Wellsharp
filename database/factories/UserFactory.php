<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\ProctorIdGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wellsharp_id' => 'WS-'.strtoupper(Str::random(10)),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'test-password-123',
            'status' => 'active',
            'remember_token' => Str::random(10),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function ($user): void {
            $user->profile()->create(['first_name' => fake()->firstName(), 'last_name' => fake()->lastName()]);
            if ($user->current_role_id) {
                RoleAssignment::create(['user_id' => $user->id, 'role_id' => $user->current_role_id, 'started_at' => now()]);
            }
            $role = $user->currentRole()->first();
            if (in_array($role?->key, [Role::PROCTOR, Role::INSTRUCTOR], true)) {
                $user->examControlCredential()->create(['control_id' => app(ProctorIdGenerator::class)->generate()]);
            }
        });
    }

    public function withRole(string $role): static
    {
        return $this->afterMaking(function (User $user) use ($role): void {
            $user->forceFill(['current_role_id' => Role::where('key', $role)->value('id')]);
        });
    }

    public function admin(): static
    {
        return $this->withRole(Role::ADMIN);
    }

    public function proctor(): static
    {
        return $this->withRole(Role::PROCTOR);
    }

    public function instructor(): static
    {
        return $this->withRole(Role::INSTRUCTOR);
    }

    public function student(): static
    {
        return $this->withRole(Role::STUDENT);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
}
