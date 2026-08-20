<?php

namespace Database\Factories;

use App\Enums\ExamGroupAssignmentStatus;
use App\Models\Exam;
use App\Models\ExamGroupAssignment;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExamGroupAssignmentFactory extends Factory
{
    protected $model = ExamGroupAssignment::class;

    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'group_id' => Group::factory(),
            'status' => ExamGroupAssignmentStatus::Active,
            'assigned_by_user_id' => null,
            'assigned_at' => now(),
            'removed_at' => null,
        ];
    }

    public function removed(): static
    {
        return $this->state(fn (): array => [
            'status' => ExamGroupAssignmentStatus::Removed,
            'assigned_at' => now()->subDays(30),
            'removed_at' => now()->subDays(7),
        ]);
    }
}
