<?php

namespace App\Services;

use App\Enums\ExamScheduleStatus;
use App\Enums\GroupMembershipStatus;
use App\Models\ExamSchedule;
use App\Models\StudentSurvey;
use App\Models\User;

class StudentExamFlowService
{
    public function assertStudentCanAccess(ExamSchedule $schedule, User $student): void
    {
        abort_unless($schedule->status === ExamScheduleStatus::Scheduled, 422, 'This class is no longer available.');
        if (! $schedule->override_started_at && $schedule->end_date?->endOfDay()->isPast()) {
            abort(422, 'This class is no longer available.');
        }

        abort_unless($schedule->group_id && $schedule->group()->whereHas('students', fn ($query) => $query
            ->where('users.id', $student->getKey())
            ->where('group_memberships.status', GroupMembershipStatus::Active->value)
        )->exists(), 403);
    }

    public function surveyFor(ExamSchedule $schedule, User $student): StudentSurvey
    {
        return StudentSurvey::query()->firstOrCreate([
            'student_user_id' => $student->getKey(),
            'exam_schedule_id' => $schedule->getKey(),
        ], ['status' => 'started']);
    }

    public function assertContactConfirmed(ExamSchedule $schedule, User $student): StudentSurvey
    {
        $this->assertStudentCanAccess($schedule, $student);
        $survey = $this->surveyFor($schedule, $student);
        abort_unless($survey->contact_confirmed_at, 422, 'Review and confirm your contact information first.');

        return $survey;
    }

    public function assertSurveyCompleted(ExamSchedule $schedule, User $student): StudentSurvey
    {
        $survey = $this->assertContactConfirmed($schedule, $student);
        abort_unless($survey->status === 'completed', 422, 'Complete the survey before starting the exam.');

        return $survey;
    }

    public function assertExamLaunchAvailable(ExamSchedule $schedule, User $student): void
    {
        $this->assertSurveyCompleted($schedule, $student);

        if (! $schedule->override_started_at && $schedule->end_date?->endOfDay()->isPast()) {
            abort(422, 'This exam schedule has ended.');
        }
    }

    public function assertReadyForExam(ExamSchedule $schedule, User $student): void
    {
        $this->assertSurveyCompleted($schedule, $student);
    }
}
