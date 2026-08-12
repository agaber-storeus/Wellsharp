<?php

namespace App\Actions\Classes;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WithdrawStudentAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(Enrollment $enrollment): Enrollment
    {
        return DB::transaction(function () use ($enrollment): Enrollment {
            $locked = Enrollment::query()->lockForUpdate()->findOrFail($enrollment->getKey());
            if ($locked->status !== EnrollmentStatus::Enrolled) {
                throw ValidationException::withMessages(['enrollment' => 'Only active enrollments can be withdrawn.']);
            }
            $before = $locked->load('student', 'trainingClass')->toArray();
            $locked->forceFill(['status' => EnrollmentStatus::Withdrawn, 'withdrawn_at' => now()])->save();
            $this->audit->record('enrollment.withdrawn', $locked, $before, $locked->fresh('student', 'trainingClass')->toArray());

            return $locked->fresh('student', 'trainingClass');
        });
    }
}
