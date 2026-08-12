<?php

namespace App\Actions\Classes;

use App\Enums\StaffAssignmentRole;
use App\Enums\StaffAssignmentStatus;
use App\Models\ClassStaffAssignment;
use App\Models\TrainingClass;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignClassStaffAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(TrainingClass $trainingClass, User $staff, StaffAssignmentRole $assignmentRole): ClassStaffAssignment
    {
        return DB::transaction(function () use ($trainingClass, $staff, $assignmentRole): ClassStaffAssignment {
            $class = TrainingClass::query()->lockForUpdate()->findOrFail($trainingClass->getKey());
            $staff = User::query()->with('currentRole')->lockForUpdate()->findOrFail($staff->getKey());

            if (! $staff->isActive() || ! $staff->hasRole($assignmentRole->value)) {
                throw ValidationException::withMessages(['user_id' => "Only active {$assignmentRole->label()} accounts can receive this assignment."]);
            }

            $assignment = ClassStaffAssignment::where('class_id', $class->getKey())->where('user_id', $staff->getKey())->where('assignment_role', $assignmentRole)->first();
            if ($assignment?->status === StaffAssignmentStatus::Active) {
                throw ValidationException::withMessages(['user_id' => 'This staff member is already assigned to the class.']);
            }

            $before = $assignment?->toArray();
            if ($assignment) {
                $assignment->forceFill(['status' => StaffAssignmentStatus::Active, 'assigned_at' => now(), 'ended_at' => null, 'assigned_by_user_id' => auth()->id()])->save();
            } else {
                $assignment = ClassStaffAssignment::create(['class_id' => $class->getKey(), 'user_id' => $staff->getKey(), 'assignment_role' => $assignmentRole, 'status' => StaffAssignmentStatus::Active, 'assigned_by_user_id' => auth()->id(), 'assigned_at' => now()]);
            }
            $this->audit->record('class_staff_assignment.created', $assignment, $before, $assignment->fresh('user', 'trainingClass')->toArray());

            return $assignment->fresh('user', 'trainingClass');
        });
    }
}
