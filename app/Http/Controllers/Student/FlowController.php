<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ExamSchedule;
use App\Models\TranslationLanguage;
use App\Services\StudentExamFlowService;
use App\Services\StudentSurveyDefinition;
use App\Services\Translation\QuestionTranslationService;
use App\Services\Translation\TranslationProviderInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FlowController extends Controller
{
    public function confirm(ExamSchedule $schedule, StudentExamFlowService $flow): View
    {
        $flow->assertStudentCanAccess($schedule, auth()->user());
        $schedule->load(['exam.subject.level', 'exam.subject.languages', 'group', 'trainingClass']);

        return view('student.confirm', [
            'schedule' => $schedule,
            'user' => auth()->user()->load('profile'),
            'studentFlowSchedule' => $schedule,
            'flowStep' => 'confirm',
        ]);
    }

    public function confirmStore(ExamSchedule $schedule, StudentExamFlowService $flow): RedirectResponse
    {
        $flow->assertStudentCanAccess($schedule, auth()->user());
        $survey = $flow->surveyFor($schedule, auth()->user());
        $survey->update(['contact_confirmed_at' => now(), 'status' => $survey->status === 'completed' ? 'completed' : 'started']);

        return redirect()->route('student.survey.start', $schedule)->with('status', 'Contact information confirmed.');
    }

    public function surveyStart(ExamSchedule $schedule, StudentExamFlowService $flow): View
    {
        $survey = $flow->assertContactConfirmed($schedule, auth()->user());
        $schedule->load(['exam.subject', 'group']);

        return view('student.survey-start', compact('schedule', 'survey') + ['studentFlowSchedule' => $schedule, 'flowStep' => 'survey']);
    }

    public function surveyForm(ExamSchedule $schedule, StudentExamFlowService $flow, StudentSurveyDefinition $definition): View
    {
        $survey = $flow->assertContactConfirmed($schedule, auth()->user());
        $schedule->load(['exam.subject', 'group']);

        return view('student.survey-form', [
            'schedule' => $schedule,
            'survey' => $survey,
            'questions' => $definition->questions(),
            'studentFlowSchedule' => $schedule,
            'flowStep' => 'survey',
        ]);
    }

    public function surveyStore(Request $request, ExamSchedule $schedule, StudentExamFlowService $flow, StudentSurveyDefinition $definition): RedirectResponse
    {
        $survey = $flow->assertContactConfirmed($schedule, auth()->user());
        $rules = ['answers' => ['required', 'array']];
        foreach ($definition->questions() as $question) {
            $answerRules = $question['required'] ? ['required', 'string', 'max:5000'] : ['nullable', 'string', 'max:5000'];
            if ($question['type'] === 'select') {
                $answerRules[] = Rule::in($question['options']);
            }
            $rules['answers.'.$question['key']] = $answerRules;
        }
        $validated = $request->validate($rules);

        DB::transaction(function () use ($survey, $validated, $definition): void {
            foreach ($definition->questions() as $question) {
                $survey->answers()->updateOrCreate(
                    ['question_key' => $question['key']],
                    ['answer' => $validated['answers'][$question['key']] ?? null],
                );
            }
            $survey->update(['status' => 'completed', 'completed_at' => now()]);
        });

        return redirect()->route('student.proctor', $schedule)->with('status', 'Survey saved. Review the instructions before starting your exam.');
    }

    public function proctor(
        ExamSchedule $schedule,
        StudentExamFlowService $flow,
        QuestionTranslationService $translationService,
        TranslationProviderInterface $provider,
    ): View {
        $flow->assertExamLaunchAvailable($schedule, auth()->user());
        $schedule->load(['exam.subject.level', 'exam.subject.stacks', 'exam.subject.languages', 'group', 'trainingClass']);

        return view('student.proctor', [
            'schedule' => $schedule,
            'canStartExam' => $flow->canStartExam($schedule),
            'startAvailabilityMessage' => $flow->startAvailabilityMessage($schedule),
            'hasFinishedExam' => $flow->hasFinishedExam($schedule, auth()->user()),
            'finishedExamMessage' => $flow->finishedExamMessage($schedule, auth()->user()),
            'sourceLanguage' => $translationService->sourceLanguage(),
            'translationLanguages' => TranslationLanguage::query()
                ->where('provider', $provider->name())
                ->where('is_enabled', true)
                ->orderBy('name')
                ->get(['code', 'name', 'native_name', 'direction']),
            'studentFlowSchedule' => $schedule,
            'flowStep' => 'exam',
        ]);
    }
}
