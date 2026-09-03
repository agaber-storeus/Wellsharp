<?php

namespace App\Actions\Exams;

use App\Actions\Certificates\IssueCertificateAction;
use App\Enums\ClassControlFailureReason;
use App\Enums\ClassStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamScheduleStatus;
use App\Models\Role;
use App\Models\TrainingClass;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\ProctorIdVerifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ControlOperationalExamAction
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly ProctorIdVerifier $controlIds,
        private readonly IssueCertificateAction $issuer,
    ) {}

    /**
     * A Proctor may start/end a Class directly, with no credential entry — the
     * Proctor's ID they own is not needed for their own actions. An Instructor
     * must supply a Proctor's ID belonging to an active, eligible Proctor (never
     * their own credential, since Instructors never own a Proctor's ID) as a
     * dual-control/oversight check.
     *
     * @return array{class: TrainingClass, proctor_name: ?string, schedules_controlled: int, changed: bool}
     */
    public function executeManual(TrainingClass $trainingClass, string $action, User $actor, ?string $proctorId = null): array
    {
        $verifiedProctor = null;

        if ($actor->currentRole?->key === Role::INSTRUCTOR) {
            $verifiedProctor = $this->verifyProctorIdForManualControl($trainingClass, $action, $actor, $proctorId ?? '');
        } elseif ($actor->currentRole?->key !== Role::PROCTOR) {
            $this->recordControlAttemptFailed($trainingClass, $action, ClassControlFailureReason::UnsupportedRole, $actor);

            throw ValidationException::withMessages(['action' => 'Only an authorized Proctor or Instructor can control a Class.']);
        }

        return $this->execute($trainingClass, $action, 'manual', $actor, null, $verifiedProctor);
    }

    /**
     * Verifies the Proctor's ID the Instructor supplied and, independent of
     * whether the Class Start/End itself goes on to succeed, records a
     * dedicated security event for the verification attempt. This runs
     * before any DB::transaction() below, so a rejected Class operation can
     * never roll back the verification event, and a failed verification
     * always leaves its own event even though no Class state changes.
     */
    private function verifyProctorIdForManualControl(TrainingClass $trainingClass, string $action, User $actor, string $proctorId): User
    {
        $result = $this->controlIds->verify($proctorId);

        if (! $result->succeeded()) {
            $this->audit->record(
                'class.proctor_verification.failed',
                $trainingClass,
                null,
                ['operation' => $action, 'failure_stage' => $result->failureReason->stage(), 'failure_reason' => $result->failureReason->value],
                $result->failureReason->label(),
                $actor->getKey(),
            );

            throw ValidationException::withMessages(['proctor_id' => "Enter an active Proctor's ID."]);
        }

        $this->audit->record(
            'class.proctor_verification.succeeded',
            $trainingClass,
            null,
            ['operation' => $action, 'verified_proctor_user_id' => $result->proctor->getKey(), 'verified_proctor_wellsharp_id' => $result->proctor->wellsharp_id],
            'Proctor ID verified for '.$action,
            $actor->getKey(),
        );

        return $result->proctor;
    }

    /** @return array{class: TrainingClass, proctor_name: ?string, schedules_controlled: int, changed: bool} */
    public function executeAutomatic(TrainingClass $trainingClass, string $action, ?Carbon $now = null): array
    {
        return $this->execute($trainingClass, $action, 'automatic', null, $now ?: now());
    }

    /**
     * Runs the actual Class transition inside its own DB transaction, exactly
     * as before. The one change: for a manual (Proctor/Instructor) attempt
     * that the Class's current status rejects - whether via a thrown
     * ValidationException or a silent idempotent no-op - a
     * `class.control_attempt.failed` event is recorded *after* the
     * transaction has already exited, never inside it. A rejection inside
     * the transaction never mutates anything, so letting it roll back is
     * harmless; recording the audit event only once we're back outside means
     * it can never be undone by that same rollback. Automatic transitions
     * are untouched - no new events, no Proctor verification, exactly as
     * before.
     *
     * @return array{class: TrainingClass, proctor_name: ?string, schedules_controlled: int, changed: bool}
     */
    private function execute(TrainingClass $trainingClass, string $action, string $source, ?User $actor, ?Carbon $now = null, ?User $verifiedProctor = null): array
    {
        if (! in_array($action, ['start', 'end'], true)) {
            throw ValidationException::withMessages(['action' => 'Choose start or end.']);
        }

        try {
            $result = DB::transaction(function () use ($trainingClass, $action, $source, $actor, $now, $verifiedProctor): array {
                $class = TrainingClass::query()->lockForUpdate()->findOrFail($trainingClass->getKey());
                $clock = $now ?: now();

                if ($action === 'start') {
                    if ($class->status === ClassStatus::Active) {
                        return $this->result($class, $actor, 0, false);
                    }
                    if ($class->status !== ClassStatus::Planned) {
                        throw ValidationException::withMessages(['action' => 'Only a scheduled Class can be started.']);
                    }
                    if ($source === 'automatic' && (! $class->starts_at || $class->starts_at->isAfter($clock))) {
                        return $this->result($class, null, 0, false);
                    }
                    if ($source === 'manual' && $class->starts_at?->isAfter($clock)) {
                        throw ValidationException::withMessages(['action' => 'This exam cannot be started before its scheduled start date.']);
                    }

                    $before = $class->toArray();
                    $class->forceFill(['status' => ClassStatus::Active, 'actual_started_at' => $clock])->save();
                    $schedules = $class->examSchedules()->where('status', ExamScheduleStatus::Scheduled->value)->lockForUpdate()->get();
                    foreach ($schedules as $schedule) {
                        if ($source === 'manual') {
                            $schedule->update(['override_started_at' => $clock, 'override_started_by_user_id' => $actor?->getKey()]);
                        }
                        $this->audit->record('exam_schedule.'.$source.'_start', $schedule, null, $schedule->fresh()->toArray(), ucfirst($source).' start', $actor?->getKey());
                    }
                    $this->audit->record('class.'.$source.'_start', $class, $before, $this->withVerifiedProctor($class->fresh()->toArray(), $verifiedProctor), ucfirst($source).' start', $actor?->getKey());

                    return $this->result($class, $actor, $schedules->count(), true);
                }

                if ($class->status === ClassStatus::Completed) {
                    return $this->result($class, $actor, 0, false);
                }
                if ($class->status !== ClassStatus::Active) {
                    throw ValidationException::withMessages(['action' => 'Only an active Class can be ended.']);
                }
                if ($source === 'automatic' && (! $class->ends_at || $class->ends_at->isAfter($clock))) {
                    return $this->result($class, null, 0, false);
                }

                $before = $class->toArray();
                $class->forceFill(['status' => ClassStatus::Completed, 'actual_ended_at' => $clock])->save();
                $schedules = $class->examSchedules()->where('status', ExamScheduleStatus::Scheduled->value)->lockForUpdate()->get();
                foreach ($schedules as $schedule) {
                    $schedule->update([
                        'status' => ExamScheduleStatus::Completed,
                        'override_ended_at' => $source === 'manual' ? $clock : null,
                        'override_ended_by_user_id' => $source === 'manual' ? $actor?->getKey() : null,
                    ]);
                    $attempts = $schedule->attempts()->where('status', ExamAttemptStatus::InProgress->value)->lockForUpdate()->get();
                    foreach ($attempts as $attempt) {
                        $attempt->update(['status' => ExamAttemptStatus::Submitted->value, 'submitted_at' => $clock]);
                        $this->issuer->execute($attempt);
                    }
                    $this->audit->record('exam_schedule.'.$source.'_end', $schedule, null, $schedule->fresh()->toArray(), ucfirst($source).' end', $actor?->getKey());
                }
                $this->audit->record('class.'.$source.'_end', $class, $before, $this->withVerifiedProctor($class->fresh()->toArray(), $verifiedProctor), ucfirst($source).' end', $actor?->getKey());

                return $this->result($class, $actor, $schedules->count(), true);
            });
        } catch (ValidationException $exception) {
            if ($source === 'manual') {
                $this->recordControlAttemptFailed($trainingClass, $action, $this->classStateFailureReason($trainingClass, $action), $actor);
            }

            throw $exception;
        }

        if ($source === 'manual' && ! $result['changed']) {
            $this->recordControlAttemptFailed($trainingClass, $action, $this->classStateFailureReason($trainingClass, $action), $actor);
        }

        return $result;
    }

    /**
     * Only ever called for a manual (Proctor/Instructor) attempt that was
     * rejected purely on Class status - for `source === 'manual'` this is
     * the only way `changed` comes back false or a ValidationException is
     * thrown from inside the transaction above, so the current status
     * unambiguously explains why.
     */
    private function classStateFailureReason(TrainingClass $trainingClass, string $action): ClassControlFailureReason
    {
        $status = $trainingClass->fresh()->status;

        if ($action === 'start') {
            return match ($status) {
                ClassStatus::Active => ClassControlFailureReason::ClassAlreadyActive,
                ClassStatus::Completed => ClassControlFailureReason::ClassAlreadyCompleted,
                default => ClassControlFailureReason::ClassCancelled,
            };
        }

        return match ($status) {
            ClassStatus::Completed => ClassControlFailureReason::ClassAlreadyCompleted,
            ClassStatus::Planned => ClassControlFailureReason::ClassNotStarted,
            default => ClassControlFailureReason::ClassCancelled,
        };
    }

    private function recordControlAttemptFailed(TrainingClass $trainingClass, string $action, ClassControlFailureReason $reason, ?User $actor): void
    {
        $this->audit->record(
            'class.control_attempt.failed',
            $trainingClass,
            null,
            ['operation' => $action, 'failure_stage' => $reason->stage(), 'failure_reason' => $reason->value],
            $reason->label(),
            $actor?->getKey(),
        );
    }

    private function withVerifiedProctor(array $state, ?User $verifiedProctor): array
    {
        if (! $verifiedProctor) {
            return $state;
        }

        return $state + [
            'verified_proctor_user_id' => $verifiedProctor->getKey(),
            'verified_proctor_wellsharp_id' => $verifiedProctor->wellsharp_id,
        ];
    }

    /** @return array{class: TrainingClass, proctor_name: ?string, schedules_controlled: int, changed: bool} */
    private function result(TrainingClass $class, ?User $actor, int $schedules, bool $changed): array
    {
        return [
            'class' => $class->fresh(),
            'proctor_name' => $actor?->display_name,
            'schedules_controlled' => $schedules,
            'changed' => $changed,
        ];
    }
}
