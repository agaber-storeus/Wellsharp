<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Courses\SaveCourseAction;
use App\Enums\CourseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCourseRequest;
use App\Http\Requests\Admin\UpdateCourseRequest;
use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\Language;
use App\Models\Stack;
use App\Models\Supplement;
use App\Models\TrainingProvider;
use App\Services\AuditRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Course::class);
        $query = $this->filteredQuery($request);
        $sort = (string) $request->input('sort', 'created_at');
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['code', 'name', 'provider', 'level', 'status', 'created_at'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'created_at';

        if ($sort === 'provider') {
            $query->leftJoin('training_providers as course_provider_sort', 'course_provider_sort.id', '=', 'courses.training_provider_id')
                ->addSelect('courses.*')
                ->orderBy('course_provider_sort.name', $direction);
        } elseif ($sort === 'level') {
            $query->leftJoin('course_levels as course_level_sort', 'course_level_sort.id', '=', 'courses.course_level_id')
                ->addSelect('courses.*')
                ->orderBy('course_level_sort.name', $direction);
        } else {
            $query->orderBy('courses.'.$sort, $direction);
        }

        $courses = $query->paginate(25, ['*'], 'page', max(1, (int) $request->input('page', 1)));

        return response()->json([
            'data' => $courses->getCollection()->map(fn (Course $course): array => $this->coursePayload($course))->values(),
            'meta' => $this->paginationMeta($courses),
        ]);
    }

    public function index(): View
    {
        $this->authorize('viewAny', Course::class);
        $courses = $this->filteredQuery(request())->latest('courses.created_at')->paginate(15)->withQueryString();
        $initialCourses = $courses->getCollection()->map(fn (Course $course): array => $this->coursePayload($course))->values();

        return view('admin.courses.index', [
            'courses' => $courses,
            'initialCourses' => $initialCourses,
            'initialMeta' => $this->paginationMeta($courses),
            'search' => trim((string) request('search')),
            'status' => request('status'),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Course::class);

        return view('admin.courses.create', ['course' => new Course, ...$this->formData()]);
    }

    public function store(StoreCourseRequest $request, SaveCourseAction $action): RedirectResponse
    {
        $this->authorize('create', Course::class);
        $course = $action->execute(null, $request->validated());

        return redirect()->route('admin.courses.show', $course)->with('status', 'Subject created.');
    }

    public function show(Course $course): View
    {
        $this->authorize('view', $course);

        return view('admin.courses.show', ['course' => $course->load('provider', 'level', 'stacks', 'supplements', 'languages')]);
    }

    public function edit(Course $course): View
    {
        $this->authorize('update', $course);

        return view('admin.courses.edit', ['course' => $course->load('stacks', 'supplements', 'languages'), ...$this->formData()]);
    }

    public function update(UpdateCourseRequest $request, Course $course, SaveCourseAction $action): RedirectResponse
    {
        $this->authorize('update', $course);
        $course = $action->execute($course, $request->validated());

        return redirect()->route('admin.courses.show', $course)->with('status', 'Subject updated.');
    }

    public function archive(Course $course, AuditRecorder $audit): RedirectResponse
    {
        $this->authorize('delete', $course);
        $before = $course->toArray();
        $course->forceFill(['status' => CourseStatus::Retired, 'archived_at' => now()])->save();
        $audit->record('course.archived', $course, $before, $course->fresh()->toArray());

        return back()->with('status', 'Subject retired.');
    }

    private function formData(): array
    {
        return [
            'providers' => TrainingProvider::whereNull('archived_at')->orderBy('name')->get(),
            'levels' => CourseLevel::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'stacks' => Stack::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'supplements' => Supplement::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'languages' => Language::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ];
    }

    private function filteredQuery(Request $request)
    {
        $search = trim((string) $request->input('search'));
        $status = $request->input('status');

        return Course::query()->with(['provider', 'level'])
            ->when($search, fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('courses.code', 'like', "%{$search}%")
                    ->orWhere('courses.name', 'like', "%{$search}%")
                    ->orWhereHas('provider', fn ($provider) => $provider->where('name', 'like', "%{$search}%"));
            }))
            ->when($status, fn ($query) => $query->where('courses.status', $status));
    }

    private function coursePayload(Course $course): array
    {
        return [
            'id' => $course->getKey(),
            'code' => $course->code,
            'name' => $course->name,
            'provider' => $course->provider?->name ?: 'Not assigned',
            'level' => $course->level?->name ?: 'Not assigned',
            'status' => $course->status->value,
            'status_label' => $course->status->label(),
            'view_url' => route('admin.courses.show', $course),
        ];
    }

    private function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
