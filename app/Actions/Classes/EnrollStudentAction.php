<?php

namespace App\Actions\Classes;

use App\Enums\ClassStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\TrainingClass;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollStudentAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(TrainingClass $trainingClass, User $student): Enrollment
    {
        return DB::transaction(function () use ($trainingClass, $student): Enrollment {
            $class = TrainingClass::query()->lockForUpdate()->findOrFail($trainingClass->getKey());
            $student = User::query()->with('currentRole')->lockForUpdate()->findOrFail($student->getKey());

            if (! $student->isActive() || ! $student->hasRole('student')) {
                throw ValidationException::withMessages(['student_user_id' => 'Only active Student accounts can be enrolled.']);
            }
            if (in_array($class->status, [ClassStatus::Completed, ClassStatus::Cancelled], true)) {
                throw ValidationException::withMessages(['class' => 'Students cannot be enrolled in a completed or cancelled class.']);
            }

            $enrollment = Enrollment::where('class_id', $class->getKey())->where('student_user_id', $student->getKey())->first();
            if ($enrollment?->status === EnrollmentStatus::Enrolled) {
                throw ValidationException::withMessages(['student_user_id' => 'This Student is already enrolled in the class.']);
            }

            $before = $enrollment?->toArray();
            if ($enrollment) {
                $enrollment->forceFill(['status' => EnrollmentStatus::Enrolled, 'enrolled_at' => now(), 'withdrawn_at' => null])->save();
            } else {
                $enrollment = Enrollment::create(['class_id' => $class->getKey(), 'student_user_id' => $student->getKey(), 'status' => EnrollmentStatus::Enrolled, 'enrolled_at' => now()]);
            }
            $this->audit->record('enrollment.created', $enrollment, $before, $enrollment->fresh('student', 'trainingClass')->toArray());

            return $enrollment->fresh('student', 'trainingClass');
        });
    }
}
