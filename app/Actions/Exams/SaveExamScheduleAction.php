<?php

namespace App\Actions\Exams;

use App\Enums\ExamScheduleStatus;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\TrainingProviderLocation;
use App\Services\AuditRecorder;
use App\Services\ExamClassSynchronizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveExamScheduleAction
{
    public function __construct(private readonly AuditRecorder $audit, private readonly ExamClassSynchronizer $classSynchronizer) {}

    public function execute(?ExamSchedule $schedule, array $data): ExamSchedule
    {
        return DB::transaction(function () use ($schedule, $data): ExamSchedule {
            $exam = Exam::query()->findOrFail($data['exam_id']);
            if ($exam->status->value !== 'published') {
                throw ValidationException::withMessages(['exam_id' => 'Only published exams can be scheduled.']);
            }
            $group = Group::query()->findOrFail($data['group_id']);
            if ($group->status->value !== 'active') {
                throw ValidationException::withMessages(['group_id' => 'Only active groups can be scheduled.']);
            }
            $creating = $schedule === null;
            $providerLocationId = $this->resolveProviderLocation($schedule, $data);
            $startDate = Carbon::createFromFormat('Y-m-d', $data['start_date'])->toDateString();
            $endDate = Carbon::createFromFormat('Y-m-d', $data['end_date'])->toDateString();
            $startTime = filled($data['start_time'] ?? null) ? Carbon::createFromFormat('H:i', $data['start_time'])->format('H:i:s') : null;
            $endTime = filled($data['end_time'] ?? null) ? Carbon::createFromFormat('H:i', $data['end_time'])->format('H:i:s') : null;
            if ($creating && ExamSchedule::query()
                ->where('exam_id', $exam->getKey())
                ->where('group_id', $group->getKey())
                ->whereDate('start_date', $startDate)
                ->whereDate('end_date', $endDate)
                ->exists()) {
                throw ValidationException::withMessages(['group_id' => 'This Exam is already scheduled for this Group on these dates.']);
            }
            if (! $creating) {
                $schedule = ExamSchedule::query()->lockForUpdate()->findOrFail($schedule->getKey());
                if ($schedule->start_date?->isPast() && $schedule->status === ExamScheduleStatus::Scheduled) {
                    throw ValidationException::withMessages(['start_date' => 'Only future schedules can be edited.']);
                }
                if ($schedule->start_date?->isPast() && ((int) $schedule->exam_id !== (int) $data['exam_id'] || (int) $schedule->group_id !== (int) $data['group_id'])) {
                    throw ValidationException::withMessages(['exam_id' => 'Exam and Group cannot be changed after a schedule has started.']);
                }
                $before = $schedule->toArray();
            } else {
                $before = null;
            }
            $attributes = [
                'class_id' => filled($data['class_id'] ?? null) ? trim((string) $data['class_id']) : null,
                'stack_offered' => filled($data['stack_offered'] ?? null) ? trim((string) $data['stack_offered']) : null,
                'supplement_offered' => filled($data['supplement_offered'] ?? null) ? trim((string) $data['supplement_offered']) : 'No Supplement Offered',
                'exam_id' => $exam->getKey(), 'group_id' => $group->getKey(),
                'training_provider_id' => $data['training_provider_id'] ?? null,
                'training_provider_location_id' => $providerLocationId,
                'start_date' => $startDate,
                'start_time' => $startTime,
                'end_date' => $endDate,
                'end_time' => $endTime,
                'duration_minutes' => $data['duration_minutes'],
                'start_mode' => $data['start_mode'] ?? 'automatic',
                'updated_by_user_id' => auth()->id(),
            ];
            if ($creating) {
                $attributes['status'] = ExamScheduleStatus::Scheduled;
                $attributes['created_by_user_id'] = auth()->id();
                $schedule = ExamSchedule::create($attributes);
            } else {
                $schedule->update($attributes);
            }
            $this->classSynchronizer->sync($schedule, [
                'proctor_id' => $data['proctor_id'] ?? null,
                'instructor_id' => $data['instructor_id'] ?? null,
            ]);
            $this->audit->record($creating ? 'exam_schedule.created' : 'exam_schedule.updated', $schedule, $before, $schedule->fresh()->toArray());

            return $schedule->fresh(['exam', 'group', 'trainingClass']);
        });
    }

    private function resolveProviderLocation(?ExamSchedule $schedule, array $data): ?int
    {
        $providerId = $data['training_provider_id'] ?? null;
        $locationId = $data['training_provider_location_id'] ?? null;

        if (! $providerId) {
            if ($locationId) {
                throw ValidationException::withMessages(['training_provider_location_id' => 'Select the training provider for this location.']);
            }

            return null;
        }

        if (! $locationId) {
            $locations = TrainingProviderLocation::query()->where('training_provider_id', $providerId)->where('is_active', true)->pluck('id');
            if ($locations->count() === 1) {
                return (int) $locations->first();
            }

            throw ValidationException::withMessages(['training_provider_location_id' => 'Select a location for this training provider.']);
        }

        $location = TrainingProviderLocation::query()->find($locationId);
        $isCurrent = (int) $schedule?->training_provider_location_id === (int) $locationId;
        if (! $location || (int) $location->training_provider_id !== (int) $providerId || (! $location->is_active && ! $isCurrent)) {
            throw ValidationException::withMessages(['training_provider_location_id' => 'Select an active location belonging to the selected training provider.']);
        }

        return $location->getKey();
    }
}
