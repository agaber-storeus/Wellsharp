<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\ProctorIdGenerator;
use App\Services\UserIdentityGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Plaintext password used for every factory-created user. Kept as a
     * shared constant (not per-user) so tests can log in with a known value.
     */
    public const DEFAULT_PASSWORD = 'test-password-123';

    /**
     * Define the model's default state.
     *
     * `wellsharp_id` must be non-null/unique at insert time, but the real
     * format (see UserIdentityGenerator) is derived from the profile's
     * first/last name, which does not exist until afterCreating() below.
     * A ULID-based placeholder guarantees uniqueness at insert time and is
     * replaced with a name-derived, correctly formatted id immediately
     * after the profile is created.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wellsharp_id' => 'TMP-'.Str::ulid(),
            'email' => fake()->unique()->safeEmail(),
            'password' => self::DEFAULT_PASSWORD,
            // Explicit, not left to the `status` column's DB-level default:
            // Eloquent's in-memory model after create() only reflects
            // attributes actually sent in the INSERT, so relying on the DB
            // default here would leave $user->status null in PHP (and
            // isActive() false) for every factory-created user, breaking
            // actingAs() in tests even though the DB row itself says 'active'.
            'status' => UserStatus::Active,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            $profile = $user->profile()->create([
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
            ]);

            $identity = app(UserIdentityGenerator::class);
            $roleKey = $user->current_role_id
                ? Role::query()->whereKey($user->current_role_id)->value('key')
                : null;

            $updates = [];
            if (str_starts_with((string) $user->wellsharp_id, 'TMP-')) {
                $updates['wellsharp_id'] = $identity->generateWellsharpId($profile->first_name, $profile->last_name);
            }
            if (blank($user->username)) {
                $updates['username'] = $identity->generateUsername($profile->first_name, $profile->last_name);
            }
            // Mirrors User::setPasswordAndCiphertext(): only Students get a
            // recoverable ciphertext copy. Only set it when the password is
            // still the factory default - a caller-supplied custom password
            // (via ->state(['password' => ...])) is already hashed by this
            // point and its plaintext cannot be recovered, so leave it alone
            // rather than encrypt the wrong value.
            if ($roleKey === Role::STUDENT && Hash::check(self::DEFAULT_PASSWORD, $user->password)) {
                $updates['password_ciphertext'] = Crypt::encryptString(self::DEFAULT_PASSWORD);
            }
            if ($updates !== []) {
                $user->forceFill($updates)->save();
            }

            if ($user->current_role_id) {
                RoleAssignment::query()->firstOrCreate(
                    ['user_id' => $user->id, 'role_id' => $user->current_role_id, 'ended_at' => null],
                    ['started_at' => now(), 'assigned_by_user_id' => null],
                );
            }

            $generator = app(ProctorIdGenerator::class);
            if ($generator->eligibleForRole($roleKey)) {
                $user->examControlCredential()->firstOrCreate([], ['control_id' => $generator->generate()]);
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

    public function disabled(): static
    {
        return $this->afterMaking(function (User $user): void {
            $user->forceFill(['status' => 'disabled', 'session_version' => 2]);
        });
    }

    public function archived(): static
    {
        return $this->afterMaking(function (User $user): void {
            $user->forceFill(['status' => 'archived', 'archived_at' => now()->subDays(10), 'session_version' => 2]);
        });
    }
}
