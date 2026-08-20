<?php

namespace Database\Factories;

use App\Models\ExamControlCredential;
use App\Models\User;
use App\Services\ProctorIdGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamControlCredential>
 *
 * Defaults `user_id` to an Admin owner (not a Proctor) on purpose: creating
 * a Proctor via User::factory()->proctor() already auto-creates its own
 * credential (see UserFactory), and control_id is unique per user, so
 * nesting a fresh Proctor here would collide with that automatic insert.
 * Pass an explicit `user_id` for an existing Proctor for realistic data.
 */
class ExamControlCredentialFactory extends Factory
{
    protected $model = ExamControlCredential::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->admin(),
            'control_id' => app(ProctorIdGenerator::class)->generate(),
        ];
    }
}
