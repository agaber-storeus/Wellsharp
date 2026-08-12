<?php

namespace App\Actions\Exams;

use App\Actions\Certificates\IssueCertificateAction;
use App\Enums\ClassStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamScheduleStatus;
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

    /** @return array{class: TrainingClass, proctor_name: ?string, schedules_controlled: int, changed: bool} */
    public function executeManual(TrainingClass $trainingClass, string $action, User $actor, string $controlId): array
    {
        if (! $this->controlIds->findFor($actor, $controlId)) {
            throw ValidationException::withMessages(['proctor_id' => 'The exam-control ID does not belong to the authenticated user.']);
        }

        return $this->execute($trainingClass, $action, 'manual', $actor);
    }

    /** @return array{class: TrainingClass, proctor_name: ?string, schedules_controlled: int, changed: bool} */
    public function executeAutomatic(TrainingClass $trainingClass, string $action, ?Carbon $now = null): array
    {
        return $this->execute($trainingClass, $action, 'automatic', null, $now ?: now());
    }

    /** @return array{class: TrainingClass, proctor_name: ?string, schedules_controlled: int, changed: bool} */
    private function execute(TrainingClass $trainingClass, string $action, string $source, ?User $actor, ?Carbon $now = null): array
    {
        if (! in_array($action, ['start', 'end'], true)) {
            throw ValidationException::withMessages(['action' => 'Choose start or end.']);
        }

        return DB::transaction(function () use ($trainingClass, $action, $source, $actor, $now): array {
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

                $before = $class->toArray();
                $class->forceFill(['status' => ClassStatus::Active, 'actual_started_at' => $clock])->save();
                $schedules = $class->examSchedules()->where('status', ExamScheduleStatus::Scheduled->value)->lockForUpdate()->get();
                foreach ($schedules as $schedule) {
                    if ($source === 'manual') {
                        $schedule->update(['override_started_at' => $clock, 'override_started_by_user_id' => $actor?->getKey()]);
                    }
                    $this->audit->record('exam_schedule.'.$source.'_start', $schedule, null, $schedule->fresh()->toArray(), ucfirst($source).' start', $actor?->getKey());
                }
                $this->audit->record('class.'.$source.'_start', $class, $before, $class->fresh()->toArray(), ucfirst($source).' start', $actor?->getKey());

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
            $this->audit->record('class.'.$source.'_end', $class, $before, $class->fresh()->toArray(), ucfirst($source).' end', $actor?->getKey());

            return $this->result($class, $actor, $schedules->count(), true);
        });
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
