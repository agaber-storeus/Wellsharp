<?php

namespace App\Actions\Classes;

use App\Enums\ClassStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\TrainingClass;
use Illuminate\Support\Facades\DB;

class SyncGroupEnrollmentsAction
{
    /**
     * The Proctor/Instructor Class Dashboard roster reads from `enrollments`
     * (TrainingClass -> Enrollment -> Student), not from the Exam's Group
     * membership directly. When an Exam is scheduled against a Group, every
     * active, eligible Student in that Group must have a matching Enrollment
     * row or the roster renders empty. This mirrors the admin manual
     * "Enroll Student" action (App\Actions\Classes\EnrollStudentAction) but
     * runs in bulk for the whole Group, and only ever adds/reactivates rows —
     * it never withdraws a Student who was removed from the Group, since that
     * is not an invented business rule this fix is responsible for.
     */
    public function execute(TrainingClass $trainingClass, Group $group): void
    {
        if (in_array($trainingClass->status, [ClassStatus::Completed, ClassStatus::Cancelled], true)) {
            return;
        }

        DB::transaction(function () use ($trainingClass, $group): void {
            $class = TrainingClass::query()->lockForUpdate()->findOrFail($trainingClass->getKey());

            $studentIds = $group->students()
                ->where('users.status', 'active')
                ->whereNull('users.archived_at')
                ->whereHas('currentRole', fn ($role) => $role->where('key', 'student'))
                ->pluck('users.id');

            $existing = Enrollment::query()
                ->where('class_id', $class->getKey())
                ->whereIn('student_user_id', $studentIds)
                ->get()
                ->keyBy('student_user_id');

            foreach ($studentIds as $studentId) {
                $enrollment = $existing->get($studentId);
                if ($enrollment) {
                    if ($enrollment->status !== EnrollmentStatus::Enrolled) {
                        $enrollment->forceFill(['status' => EnrollmentStatus::Enrolled, 'enrolled_at' => now(), 'withdrawn_at' => null])->save();
                    }

                    continue;
                }

                Enrollment::create([
                    'class_id' => $class->getKey(),
                    'student_user_id' => $studentId,
                    'status' => EnrollmentStatus::Enrolled,
                    'enrolled_at' => now(),
                ]);
            }
        });
    }

    /**
     * Reconcile every Class currently backed by an Exam Schedule for a Group.
     * This is called when group membership changes, so a running Class gets
     * the same roster update as a newly-created schedule.
     */
    public function executeForGroup(Group $group): void
    {
        $group->examSchedules()
            ->whereNotNull('training_class_id')
            ->with('trainingClass')
            ->get()
            ->each(function ($schedule): void {
                if ($schedule->trainingClass) {
                    $this->execute($schedule->trainingClass, $schedule->group);
                }
            });
    }
}
