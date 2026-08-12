<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CourseStatus;
use App\Enums\ProviderStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\StudentSurvey;
use App\Models\TrainingProvider;
use App\Models\User;
use App\Services\StudentSurveyDefinition;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $completedSurveyCount = StudentSurvey::query()
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->count();
        $surveyResponses = StudentSurvey::query()
            ->with(['schedule.exam.subject', 'schedule.group', 'schedule.trainingClass', 'answers'])
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->limit(12)
            ->get();
        $surveyQuestionLabels = collect(app(StudentSurveyDefinition::class)->questions())
            ->mapWithKeys(fn (array $question): array => [$question['key'] => $question['label']])
            ->all();

        return view('admin.dashboard', [
            'userCount' => User::count(),
            'activeUserCount' => User::where('status', UserStatus::Active)->whereNull('archived_at')->count(),
            'providerCount' => TrainingProvider::where('status', ProviderStatus::Active)->whereNull('archived_at')->count(),
            'courseCount' => Course::where('status', CourseStatus::Active)->whereNull('archived_at')->count(),
            'certificateCount' => Certificate::where('status', 'issued')->count(),
            'completedSurveyCount' => $completedSurveyCount,
            'surveyResponses' => $surveyResponses,
            'surveyQuestionLabels' => $surveyQuestionLabels,
        ]);
    }
}
