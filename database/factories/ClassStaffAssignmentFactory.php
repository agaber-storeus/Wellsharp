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
        return ['class_id' => TrainingClass::factory(), 'user_id' => User::factory()->proctor(), 'assignment_role' => StaffAssignmentRole::Proctor, 'status' => 'active', 'assigned_at' => now()];
    }
}
