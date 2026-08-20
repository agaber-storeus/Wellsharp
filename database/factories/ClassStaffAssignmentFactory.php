<?php

namespace Database\Factories;

use App\Enums\StaffAssignmentRole;
use App\Models\ClassStaffAssignment;
use App\Models\TrainingClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassStaffAssignmentFactory extends Factory
{
    protected $model = ClassStaffAssignment::class;

    public function definition(): array
    {
        return [
            'class_id' => TrainingClass::factory(),
            'user_id' => User::factory()->proctor(),
            'assignment_role' => StaffAssignmentRole::Proctor,
            'status' => 'active',
            'assigned_by_user_id' => null,
            'assigned_at' => now(),
            'ended_at' => null,
        ];
    }

    public function instructor(): static
    {
        return $this->state(fn (): array => ['user_id' => User::factory()->instructor(), 'assignment_role' => StaffAssignmentRole::Instructor]);
    }

    public function ended(): static
    {
        return $this->state(fn (): array => ['status' => 'ended', 'ended_at' => now()]);
    }
}
