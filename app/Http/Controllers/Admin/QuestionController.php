<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Questions\ArchiveQuestionAction;
use App\Actions\Questions\CreateQuestionAction;
use App\Actions\Questions\ImportQuestionsAction;
use App\Actions\Questions\UpdateQuestionAction;
use App\Enums\QuestionDifficulty;
use App\Enums\QuestionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportQuestionsRequest;
use App\Http\Requests\Admin\StoreQuestionRequest;
use App\Http\Requests\Admin\UpdateQuestionRequest;
use App\Models\Course;
use App\Models\Question;
use App\Services\AuditRecorder;
use App\Services\QuestionExcelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuestionController extends Controller
{
    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Question::class);
        $search = trim((string) $request->input('search'));
        $courseId = $request->input('course_id');
        $difficulty = $request->input('difficulty');
        $type = $request->input('type');
        $status = $request->input('status');
        $sort = (string) $request->input('sort', 'created_at');
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['question_text', 'course_id', 'type', 'difficulty', 'is_active', 'created_at'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'created_at';

        $query = Question::query()->with(['course', 'options'])
            ->when($search, fn ($query) => $query->where('question_text', 'like', "%{$search}%"))
            ->when($courseId, fn ($query) => $query->where('course_id', $courseId))
            ->when(in_array($difficulty, array_column(QuestionDifficulty::cases(), 'value'), true), fn ($query) => $query->where('difficulty', $difficulty))
            ->when(in_array($type, array_column(QuestionType::cases(), 'value'), true), fn ($query) => $query->where('type', $type))
            ->when(in_array($status, ['active', 'archived'], true), fn ($query) => $query->where('is_active', $status === 'active'));

        if ($sort === 'course_id') {
            $query->leftJoin('courses as subject_sort', 'subject_sort.id', '=', 'questions.course_id')->select('questions.*')->orderBy('subject_sort.name', $direction);
        } else {
            $query->orderBy($sort, $direction);
        }

        $questions = $query->paginate(25, ['*'], 'page', max(1, (int) $request->input('page', 1)));

        return response()->json([
            'data' => $questions->getCollection()->map(fn (Question $question): array => $this->questionPayload($question))->values(),
            'meta' => [
                'current_page' => $questions->currentPage(),
                'last_page' => $questions->lastPage(),
                'total' => $questions->total(),
                'from' => $questions->firstItem(),
                'to' => $questions->lastItem(),
            ],
        ]);
    }

    public function courseData(Request $request, Course $course): JsonResponse
    {
        $this->authorize('viewAny', Question::class);
        $search = trim((string) $request->input('search'));
        $difficulty = $request->input('difficulty');
        $type = $request->input('type');
        $status = $request->input('status');
        $sort = (string) $request->input('sort', 'created_at');
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['question_text', 'type', 'difficulty', 'is_active', 'created_at'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'created_at';

        $questions = $course->questions()->with('options')
            ->when($search, fn ($query) => $query->where('question_text', 'like', "%{$search}%"))
            ->when(in_array($difficulty, array_column(QuestionDifficulty::cases(), 'value'), true), fn ($query) => $query->where('difficulty', $difficulty))
            ->when(in_array($type, array_column(QuestionType::cases(), 'value'), true), fn ($query) => $query->where('type', $type))
            ->when(in_array($status, ['active', 'archived'], true), fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderBy($sort, $direction)
            ->paginate(15, ['*'], 'page', max(1, (int) $request->input('page', 1)));

        return response()->json([
            'data' => $questions->getCollection()->map(fn (Question $question): array => $this->questionPayload($question))->values(),
            'meta' => [
                'current_page' => $questions->currentPage(),
                'last_page' => $questions->lastPage(),
                'total' => $questions->total(),
                'from' => $questions->firstItem(),
                'to' => $questions->lastItem(),
            ],
        ]);
    }

    public function all(Request $request): View
    {
        $this->authorize('viewAny', Question::class);
        $search = trim((string) $request->input('search'));
        $courseId = $request->input('course_id');
        $difficulty = $request->input('difficulty');
        $type = $request->input('type');
        $status = $request->input('status');
        $questions = Question::query()->with(['course', 'options'])
            ->when($search, fn ($query) => $query->where('question_text', 'like', "%{$search}%"))
            ->when($courseId, fn ($query) => $query->where('course_id', $courseId))
            ->when(in_array($difficulty, array_column(QuestionDifficulty::cases(), 'value'), true), fn ($query) => $query->where('difficulty', $difficulty))
            ->when(in_array($type, array_column(QuestionType::cases(), 'value'), true), fn ($query) => $query->where('type', $type))
            ->when(in_array($status, ['active', 'archived'], true), fn ($query) => $query->where('is_active', $status === 'active'))
            ->latest()->paginate(20)->withQueryString();

        $initialQuestions = $questions->getCollection()->map(fn (Question $question): array => $this->questionPayload($question))->values();

        return view('admin.questions.all', compact('questions', 'initialQuestions', 'search', 'courseId', 'difficulty', 'type', 'status') + ['subjects' => Course::query()->orderBy('name')->get(), 'types' => QuestionType::cases(), 'difficulties' => QuestionDifficulty::cases()]);
    }

    public function createGlobal(): View
    {
        $this->authorize('create', Question::class);
        $subjects = Course::query()->where('status', 'active')->whereNull('archived_at')->orderBy('name')->get();

        return view('admin.questions.create-global', [
            'subjects' => $subjects,
            'question' => new Question,
            'types' => QuestionType::cases(),
            'difficulties' => QuestionDifficulty::cases(),
            'storeUrls' => $subjects->mapWithKeys(fn (Course $subject): array => [(string) $subject->getKey() => route('admin.courses.questions.store', $subject)])->all(),
            'selectedCourseId' => (string) old('course_id', ''),
        ]);
    }

    public function importGlobal(): View
    {
        $this->authorize('viewAny', Question::class);
        $subjects = Course::query()->where('status', 'active')->whereNull('archived_at')->orderBy('name')->get();

        return view('admin.questions.import-global', [
            'subjects' => $subjects,
            'selectedCourseId' => (string) old('course_id', ''),
            'templateUrls' => $subjects->mapWithKeys(fn (Course $subject): array => [(string) $subject->getKey() => route('admin.courses.questions.template', $subject)])->all(),
            'previewUrls' => $subjects->mapWithKeys(fn (Course $subject): array => [(string) $subject->getKey() => route('admin.courses.questions.import.preview', $subject)])->all(),
        ]);
    }

    public function index(Request $request, Course $course): View
    {
        $this->authorize('viewAny', Question::class);
        $search = trim((string) $request->input('search'));
        $difficulty = $request->input('difficulty');
        $type = $request->input('type');
        $status = $request->input('status');
        $questions = $course->questions()->with('options')
            ->when($search, fn ($query) => $query->where('question_text', 'like', "%{$search}%"))
            ->when(in_array($difficulty, array_column(QuestionDifficulty::cases(), 'value'), true), fn ($query) => $query->where('difficulty', $difficulty))
            ->when(in_array($type, array_column(QuestionType::cases(), 'value'), true), fn ($query) => $query->where('type', $type))
            ->when(in_array($status, ['active', 'archived'], true), fn ($query) => $query->where('is_active', $status === 'active'))
            ->latest()->paginate(15)->withQueryString();

        $initialQuestions = $questions->getCollection()->map(fn (Question $question): array => $this->questionPayload($question))->values();

        return view('admin.questions.index', compact('course', 'questions', 'initialQuestions', 'search', 'difficulty', 'type', 'status'));
    }

    public function create(Course $course): View
    {
        $this->authorize('create', Question::class);

        return view('admin.questions.create', ['course' => $course, 'question' => new Question, 'types' => QuestionType::cases(), 'difficulties' => QuestionDifficulty::cases()]);
    }

    public function store(StoreQuestionRequest $request, Course $course, CreateQuestionAction $action): RedirectResponse
    {
        $this->authorize('create', Question::class);
        $action->execute($course, $request->validated());

        if ($request->boolean('create_another')) {
            $route = $request->input('creation_source') === 'global' ? 'admin.questions.create' : 'admin.courses.questions.create';

            return redirect()->route($route, $route === 'admin.questions.create' ? [] : [$course])->with('status', 'Question created. You can add another Question.');
        }

        return redirect()->route('admin.courses.questions.index', $course)->with('status', 'Question created.');
    }

    public function edit(Course $course, Question $question): View
    {
        $this->ensureCourseQuestion($course, $question);
        $this->authorize('update', $question);

        return view('admin.questions.edit', ['course' => $course, 'question' => $question->load('options'), 'types' => QuestionType::cases(), 'difficulties' => QuestionDifficulty::cases()]);
    }

    public function update(UpdateQuestionRequest $request, Course $course, Question $question, UpdateQuestionAction $action): RedirectResponse
    {
        $this->ensureCourseQuestion($course, $question);
        $this->authorize('update', $question);
        $action->execute($question, $request->validated());

        return redirect()->route('admin.courses.questions.index', $course)->with('status', 'Question updated.');
    }

    public function destroy(Request $request, Course $course, Question $question, ArchiveQuestionAction $action): RedirectResponse|JsonResponse
    {
        $this->ensureCourseQuestion($course, $question);
        $this->authorize('delete', $question);
        $action->execute($question);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Question archived.', 'active' => false, 'status_label' => 'Archived']);
        }

        return back()->with('status', 'Question archived.');
    }

    public function unarchive(Request $request, Course $course, Question $question, AuditRecorder $audit): RedirectResponse|JsonResponse
    {
        $this->ensureCourseQuestion($course, $question);
        $this->authorize('update', $question);
        abort_unless($question->is_active === false, 422, 'This question is not archived.');
        $before = ['is_active' => false];
        $question->forceFill(['is_active' => true, 'updated_by_user_id' => auth()->id()])->save();
        $audit->record('question.unarchived', $question, $before, ['is_active' => true]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Question unarchived and restored to Active.', 'active' => true, 'status_label' => 'Active']);
        }

        return back()->with('status', 'Question unarchived and restored to Active.');
    }

    public function template(Course $course, QuestionExcelService $service)
    {
        $this->authorize('viewAny', Question::class);

        try {
            return $service->template();
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['questions_file' => $exception->getMessage()]);
        }
    }

    public function import(Course $course): View
    {
        $this->authorize('viewAny', Question::class);

        return view('admin.questions.import', ['course' => $course, 'preview' => session($this->previewKey($course))]);
    }

    public function preview(ImportQuestionsRequest $request, Course $course, QuestionExcelService $service): RedirectResponse
    {
        $this->authorize('viewAny', Question::class);
        try {
            $preview = $service->preview($course, $request->file('questions_file'));
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages(['questions_file' => $exception->getMessage() ?: 'The spreadsheet could not be read.']);
        }
        session()->put($this->previewKey($course), ['course_id' => $course->getKey(), ...$preview]);

        return redirect()->route('admin.courses.questions.import', $course)->with('status', 'Spreadsheet validated. Review the preview before confirming.');
    }

    public function confirm(Request $request, Course $course, ImportQuestionsAction $action): RedirectResponse
    {
        $this->authorize('viewAny', Question::class);
        $preview = session($this->previewKey($course));
        if (! is_array($preview) || (int) ($preview['course_id'] ?? 0) !== $course->getKey()) {
            throw ValidationException::withMessages(['questions_file' => 'The import preview has expired. Upload the file again.']);
        }
        if (($preview['invalid_count'] ?? 1) > 0 || ($preview['valid_count'] ?? 0) < 1) {
            throw ValidationException::withMessages(['questions_file' => 'The import cannot be confirmed while invalid rows remain.']);
        }
        $rows = array_map(fn (array $row): array => $row['payload'], $preview['rows']);
        try {
            $count = $action->execute($course, $rows, (string) $preview['filename']);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['questions_file' => $exception->getMessage()]);
        }
        session()->forget($this->previewKey($course));

        return redirect()->route('admin.courses.questions.index', $course)->with('status', "{$count} questions imported.");
    }

    private function ensureCourseQuestion(Course $course, Question $question): void
    {
        abort_unless($question->course_id === $course->getKey(), 404);
    }

    private function previewKey(Course $course): string
    {
        return 'admin.question_import.'.$course->getRouteKey();
    }

    private function questionPayload(Question $question): array
    {
        return [
            'id' => $question->getKey(),
            'code' => $question->code,
            'question_text' => $question->display_question_text,
            'subject' => $question->course?->name,
            'type' => $question->type->value,
            'type_label' => $question->type->label(),
            'difficulty' => $question->difficulty->value,
            'difficulty_label' => $question->difficulty->label(),
            'question_image_url' => $question->question_image_path ? Storage::disk('public')->url($question->question_image_path) : null,
            'answer_images_count' => $question->options->whereNotNull('image_path')->count() + ($question->correct_answer_image_path ? 1 : 0),
            'options_count' => $question->type === QuestionType::Mcq ? $question->options->count() : null,
            'active' => $question->is_active,
            'edit_url' => route('admin.courses.questions.edit', [$question->course, $question]),
            'archive_url' => route('admin.courses.questions.destroy', [$question->course, $question]),
            'can_unarchive' => ! $question->is_active,
            'unarchive_url' => route('admin.courses.questions.unarchive', [$question->course, $question]),
        ];
    }
}
