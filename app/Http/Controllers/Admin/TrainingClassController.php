<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Classes\CancelTrainingClassAction;
use App\Actions\Classes\EnrollStudentAction;
use App\Actions\Classes\WithdrawStudentAction;
use App\Enums\ClassStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EnrollStudentRequest;
use App\Http\Requests\Admin\StoreClassRequest;
use App\Http\Requests\Admin\UpdateClassRequest;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\TrainingClass;
use App\Models\TrainingProvider;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrainingClassController extends Controller
{
    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TrainingClass::class);
        $query = $this->filteredQuery($request);
        $sort = (string) $request->input('sort', 'created_at');
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['class_number', 'course', 'provider', 'starts_at', 'ends_at', 'status', 'created_at'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'created_at';

        if ($sort === 'course') {
            $query->join('courses as class_course_sort', 'class_course_sort.id', '=', 'classes.course_id')
                ->addSelect('classes.*')
                ->orderBy('class_course_sort.name', $direction);
        } elseif ($sort === 'provider') {
            $query->leftJoin('training_providers as class_provider_sort', 'class_provider_sort.id', '=', 'classes.training_provider_id')
                ->addSelect('classes.*')
                ->orderBy('class_provider_sort.name', $direction);
        } else {
            $query->orderBy('classes.'.$sort, $direction);
        }

        $classes = $query->paginate(25, ['*'], 'page', max(1, (int) $request->input('page', 1)));

        return response()->json([
            'data' => $classes->getCollection()->map(fn (TrainingClass $trainingClass): array => $this->classPayload($trainingClass))->values(),
            'meta' => $this->paginationMeta($classes),
        ]);
    }

    public function index(): View
    {
        $this->authorize('viewAny', TrainingClass::class);
        $classes = $this->filteredQuery(request())->latest('classes.created_at')->paginate(15)->withQueryString();
        $initialClasses = $classes->getCollection()->map(fn (TrainingClass $trainingClass): array => $this->classPayload($trainingClass))->values();

        return view('admin.classes.index', [
            'classes' => $classes,
            'initialClasses' => $initialClasses,
            'initialMeta' => $this->paginationMeta($classes),
            'search' => trim((string) request('search')),
            'status' => request('status'),
            'courseId' => request('course_id'),
            'providerId' => request('provider_id'),
            'courses' => Course::query()->orderBy('name')->get(),
            'providers' => TrainingProvider::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', TrainingClass::class);

        return view('admin.classes.create', ['trainingClass' => new TrainingClass, ...$this->formData()]);
    }

    public function store(StoreClassRequest $request, AuditRecorder $audit): RedirectResponse
    {
        $this->authorize('create', TrainingClass::class);
        $data = $request->validated();
        $trainingClass = TrainingClass::create([...$data, 'class_number' => strtoupper($data['class_number']), 'status' => ClassStatus::Planned]);
        $audit->record('class.created', $trainingClass, null, $trainingClass->load('course', 'provider')->toArray());

        return redirect()->route('admin.classes.show', $trainingClass)->with('status', 'Class created.');
    }

    public function show(TrainingClass $trainingClass): View
    {
        $this->authorize('view', $trainingClass);
        $trainingClass->load(['course', 'provider', 'enrollments.student.profile']);

        return view('admin.classes.show', [
            'trainingClass' => $trainingClass,
            'students' => User::query()->with('profile')->whereHas('currentRole', fn ($role) => $role->where('key', 'student'))->where('status', 'active')->whereNull('archived_at')->orderBy('wellsharp_id')->get(),
        ]);
    }

    public function edit(TrainingClass $trainingClass): View
    {
        $this->authorize('update', $trainingClass);

        return view('admin.classes.edit', ['trainingClass' => $trainingClass, ...$this->formData()]);
    }

    public function update(UpdateClassRequest $request, TrainingClass $trainingClass, AuditRecorder $audit): RedirectResponse
    {
        $this->authorize('update', $trainingClass);
        $before = $trainingClass->load('course', 'provider')->toArray();
        $data = $request->validated();
        $trainingClass->update([...$data, 'class_number' => strtoupper($data['class_number'])]);
        $audit->record('class.updated', $trainingClass, $before, $trainingClass->fresh('course', 'provider')->toArray());

        return redirect()->route('admin.classes.show', $trainingClass)->with('status', 'Class updated.');
    }

    public function cancel(TrainingClass $trainingClass, CancelTrainingClassAction $action): RedirectResponse
    {
        $this->authorize('delete', $trainingClass);
        $action->execute($trainingClass);

        return back()->with('status', 'Class cancelled.');
    }

    public function enroll(EnrollStudentRequest $request, TrainingClass $trainingClass, EnrollStudentAction $action): RedirectResponse
    {
        $action->execute($trainingClass, User::findOrFail($request->integer('student_user_id')));

        return back()->with('status', 'Student enrolled.');
    }

    public function withdraw(TrainingClass $trainingClass, Enrollment $enrollment, WithdrawStudentAction $action): RedirectResponse
    {
        abort_unless($enrollment->class_id === $trainingClass->getKey(), 404);
        $action->execute($enrollment);

        return back()->with('status', 'Student withdrawn.');
    }

    private function formData(): array
    {
        return ['courses' => Course::query()->where('status', 'active')->whereNull('archived_at')->orderBy('code')->get(), 'providers' => TrainingProvider::query()->where('status', 'active')->whereNull('archived_at')->orderBy('name')->get()];
    }

    private function filteredQuery(Request $request)
    {
        $search = trim((string) $request->input('search'));
        $status = $request->input('status');
        $courseId = $request->input('course_id');
        $providerId = $request->input('provider_id');

        return TrainingClass::query()->with(['course', 'provider'])
            ->when($search, fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('classes.class_number', 'like', "%{$search}%")
                    ->orWhereHas('course', fn ($course) => $course->where('name', 'like', "%{$search}%"));
            }))
            ->when($status, fn ($query) => $query->where('classes.status', $status))
            ->when($courseId, fn ($query) => $query->where('classes.course_id', $courseId))
            ->when($providerId, fn ($query) => $query->where('classes.training_provider_id', $providerId));
    }

    private function classPayload(TrainingClass $trainingClass): array
    {
        return [
            'id' => $trainingClass->getKey(),
            'class_number' => $trainingClass->class_number,
            'course' => $trainingClass->course?->name ?: 'Not assigned',
            'provider' => $trainingClass->provider?->name ?: 'Not assigned',
            'starts_at' => $trainingClass->starts_at?->format('M j, Y H:i') ?: 'Not scheduled',
            'ends_at' => $trainingClass->ends_at?->format('M j, Y H:i') ?: '—',
            'status' => $trainingClass->status->value,
            'status_label' => $trainingClass->status->label(),
            'view_url' => route('admin.classes.show', $trainingClass),
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
