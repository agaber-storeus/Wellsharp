<?php

namespace App\Actions\Exams;

use App\Enums\ExamScheduleStatus;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\Group;
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
            $startDate = Carbon::createFromFormat('Y-m-d', $data['start_date'])->toDateString();
            $endDate = Carbon::createFromFormat('Y-m-d', $data['end_date'])->toDateString();
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
                'exam_id' => $exam->getKey(), 'group_id' => $group->getKey(),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'duration_minutes' => $data['duration_minutes'],
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
}
