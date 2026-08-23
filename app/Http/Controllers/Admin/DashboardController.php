<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentSurvey;
use App\Services\AdminDashboardService;
use App\Services\StudentSurveyDefinition;

class DashboardController extends Controller
{
    public function __invoke(AdminDashboardService $dashboard)
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
            ->limit(8)
            ->get();
        $surveyQuestionLabels = collect(app(StudentSurveyDefinition::class)->questions())
            ->mapWithKeys(fn (array $question): array => [$question['key'] => $question['label']])
            ->all();

        return view('admin.dashboard', [
            'dashboard' => $dashboard->build(),
            'completedSurveyCount' => $completedSurveyCount,
            'surveyResponses' => $surveyResponses,
            'surveyQuestionLabels' => $surveyQuestionLabels,
        ]);
    }
}
