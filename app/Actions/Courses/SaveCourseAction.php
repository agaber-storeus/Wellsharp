<?php

namespace App\Actions\Courses;

use App\Enums\CourseStatus;
use App\Models\Course;
use App\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;

class SaveCourseAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(?Course $course, array $data): Course
    {
        return DB::transaction(function () use ($course, $data): Course {
            $creating = $course === null;

            if ($creating) {
                $course = Course::create([
                    'code' => strtoupper(trim($data['code'])),
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'training_provider_id' => $data['training_provider_id'] ?? null,
                    'course_level_id' => $data['course_level_id'] ?? null,
                    'status' => CourseStatus::Active,
                ]);
                $before = null;
            } else {
                $course = Course::query()->lockForUpdate()->findOrFail($course->getKey());
                $before = $course->load('provider', 'level', 'stacks', 'supplements', 'languages')->toArray();
                $course->update([
                    'code' => strtoupper(trim($data['code'])),
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'training_provider_id' => $data['training_provider_id'] ?? null,
                    'course_level_id' => $data['course_level_id'] ?? null,
                ]);
            }

            $course->stacks()->sync($data['stack_ids'] ?? []);
            $course->supplements()->sync($data['supplement_ids'] ?? []);
            $course->languages()->sync($data['language_ids'] ?? []);

            $course = $course->fresh('provider', 'level', 'stacks', 'supplements', 'languages');
            $this->audit->record($creating ? 'course.created' : 'course.updated', $course, $before, $course->toArray());

            return $course;
        });
    }
}
