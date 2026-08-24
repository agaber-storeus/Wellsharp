<?php

namespace App\Services;

use App\Enums\ClassStatus;
use App\Models\AuditEvent;
use App\Models\Course;
use App\Models\Group;
use App\Models\Role;
use App\Models\TrainingClass;
use App\Models\TrainingProvider;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Builds the entire Admin Dashboard payload in one pass: a small, fixed
 * number of indexed aggregate queries (grouped counts, conditional sums,
 * bounded top-N/limit lists) rather than one query per card or hydrating
 * the full Class/Enrollment/Attempt graph into PHP. Every pass/fail or
 * score decision routes through EffectiveScoreService::resolve() - this
 * class never re-derives "did the trainee pass" itself.
 */
class AdminDashboardService
{
    public function __construct(private readonly EffectiveScoreService $effectiveScore) {}

    /** @return array<string, mixed> */
    public function build(): array
    {
        $classStatus = $this->classStatus();
        $enrollment = $this->enrollmentOverview();
        $exam = $this->examPerformance();
        $certificates = $this->certificateOverview();
        $staff = $this->staffOverview();
        $subjects = $this->subjectsOverview();

        return [
            'kpis' => $this->kpis($classStatus, $enrollment, $exam['performance'], $certificates, $staff),
            'class_status' => $classStatus,
            'enrollment' => $enrollment,
            'exam_performance' => $exam['performance'],
            'skills_overrides' => $exam['overrides'],
            'certificates' => $certificates,
            'staff' => $staff,
            'users' => $this->usersOverview(),
            'providers' => $this->providersOverview(),
            'subjects' => $subjects,
            'groups' => $this->groupsOverview(),
            'upcoming_classes' => $this->upcomingClasses(),
            'attention' => $this->attentionItems($classStatus, $subjects),
            'recent_activity' => $this->recentActivity(),
        ];
    }

    /** @return array<string, mixed> */
    private function usersOverview(): array
    {
        $statusCounts = DB::table('users')
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $byRoleTotal = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.current_role_id')
            ->select('roles.key', DB::raw('count(*) as aggregate'))
            ->groupBy('roles.key')
            ->pluck('aggregate', 'roles.key');

        $byRoleActive = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.current_role_id')
            ->where('users.status', 'active')->whereNull('users.archived_at')
            ->select('roles.key', DB::raw('count(*) as aggregate'))
            ->groupBy('roles.key')
            ->pluck('aggregate', 'roles.key');

        $byRole = collect([Role::ADMIN, Role::PROCTOR, Role::INSTRUCTOR, Role::STUDENT])
            ->map(fn (string $key): array => [
                'key' => $key,
                'label' => str($key)->headline().'s',
                'total' => (int) ($byRoleTotal[$key] ?? 0),
                'active' => (int) ($byRoleActive[$key] ?? 0),
            ])->values()->all();

        return [
            'total' => (int) $statusCounts->sum(),
            'active' => (int) ($statusCounts['active'] ?? 0),
            'disabled' => (int) ($statusCounts['disabled'] ?? 0),
            'archived' => (int) ($statusCounts['archived'] ?? 0),
            'by_role' => $byRole,
        ];
    }

    /** @return array<string, mixed> */
    private function providersOverview(): array
    {
        $statusCounts = DB::table('training_providers')
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $top = TrainingProvider::query()
            ->where('status', 'active')->whereNull('archived_at')
            ->withCount('classes')
            ->orderByDesc('classes_count')
            ->limit(5)->get()
            ->map(fn (TrainingProvider $provider): array => ['name' => $provider->name, 'count' => $provider->classes_count])
            ->values()->all();

        return [
            'total' => (int) $statusCounts->sum(),
            'active' => (int) ($statusCounts['active'] ?? 0),
            'inactive' => (int) ($statusCounts['inactive'] ?? 0),
            'archived' => (int) ($statusCounts['archived'] ?? 0),
            'top' => $top,
        ];
    }

    /** @return array<string, mixed> */
    private function subjectsOverview(): array
    {
        $statusCounts = DB::table('courses')
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $withoutExam = Course::query()->where('status', 'active')->whereNull('archived_at')->doesntHave('exams')->count();

        $questionCounts = DB::table('questions')->selectRaw(
            'count(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active'
        )->first();

        $questionsByType = DB::table('questions')
            ->select('type', DB::raw('count(*) as aggregate'))
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        return [
            'courses_total' => (int) $statusCounts->sum(),
            'courses_active' => (int) ($statusCounts['active'] ?? 0),
            'courses_retired' => (int) ($statusCounts['retired'] ?? 0),
            'courses_without_exam' => $withoutExam,
            'questions_total' => (int) ($questionCounts->total ?? 0),
            'questions_active' => (int) ($questionCounts->active ?? 0),
            'questions_mcq' => (int) ($questionsByType['mcq'] ?? 0),
            'questions_true_false' => (int) ($questionsByType['true_false'] ?? 0),
            'questions_input' => (int) ($questionsByType['input'] ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function groupsOverview(): array
    {
        $statusCounts = DB::table('student_groups')
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $scheduleCounts = DB::table('exam_schedules')
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $top = Group::query()
            ->where('status', 'active')
            ->withCount('students')
            ->orderByDesc('students_count')
            ->limit(5)->get()
            ->map(fn (Group $group): array => ['name' => $group->name, 'count' => $group->students_count])
            ->values()->all();

        return [
            'total' => (int) $statusCounts->sum(),
            'active' => (int) ($statusCounts['active'] ?? 0),
            'archived' => (int) ($statusCounts['archived'] ?? 0),
            'schedules_scheduled' => (int) ($scheduleCounts['scheduled'] ?? 0),
            'schedules_completed' => (int) ($scheduleCounts['completed'] ?? 0),
            'schedules_cancelled' => (int) ($scheduleCounts['cancelled'] ?? 0),
            'top' => $top,
        ];
    }

    /** @return array<string, mixed> */
    private function classStatus(): array
    {
        $counts = DB::table('classes')
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $total = (int) $counts->sum();

        $breakdown = collect(ClassStatus::cases())->map(function (ClassStatus $status) use ($counts, $total): array {
            $count = (int) ($counts[$status->value] ?? 0);

            return [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => $count,
                'percent' => $total > 0 ? round($count / $total * 100, 1) : 0.0,
            ];
        })->values()->all();

        $legacyUnassigned = TrainingClass::query()
            ->where(fn ($query) => $query->whereNull('proctor_id')->orWhereNull('instructor_id'))
            ->count();

        return [
            'total' => $total,
            'breakdown' => $breakdown,
            'counts' => $counts,
            'legacy_unassigned' => $legacyUnassigned,
        ];
    }

    /** @return array<string, mixed> */
    private function enrollmentOverview(): array
    {
        $counts = DB::table('enrollments')
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $total = (int) $counts->sum();
        $uniqueStudents = DB::table('enrollments')->distinct()->count('student_user_id');
        $studentsWithAttempts = DB::table('exam_attempts')->distinct()->count('student_user_id');

        $studentsWithoutAttempts = DB::table('enrollments')
            ->whereNotIn('student_user_id', function ($query): void {
                $query->select('student_user_id')->from('exam_attempts');
            })
            ->distinct()->count('student_user_id');

        return [
            'total' => $total,
            'unique_students' => $uniqueStudents,
            'by_status' => $counts,
            'students_with_attempts' => $studentsWithAttempts,
            'students_without_attempts' => $studentsWithoutAttempts,
        ];
    }

    /**
     * Fetches only the scalar columns needed to run every scored attempt
     * through EffectiveScoreService::resolve() - no relations, no full
     * Class/Enrollment models. The row count here is bounded by "attempts
     * that have been scored", not by every Class/Enrollment in the system.
     *
     * @return array<string, mixed>
     */
    private function examPerformance(): array
    {
        $statusCounts = DB::table('exam_attempts')
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $scoredAttempts = DB::table('exam_attempts')
            ->join('exam_schedules', 'exam_schedules.id', '=', 'exam_attempts.exam_schedule_id')
            ->join('exams', 'exams.id', '=', 'exam_attempts.exam_id')
            ->whereNotNull('exam_attempts.score')
            ->select([
                'exam_attempts.student_user_id',
                'exam_attempts.score',
                'exam_attempts.passed as raw_passed',
                'exam_schedules.training_class_id',
                'exams.passing_score',
            ])
            ->get();

        $skillsScores = DB::table('enrollments')
            ->whereNotNull('skills_score')
            ->select(['class_id', 'student_user_id', 'skills_score'])
            ->get()
            ->keyBy(fn ($row) => $row->class_id.':'.$row->student_user_id);

        $passed = 0;
        $failed = 0;
        $scoreSum = 0.0;
        $scoreCount = 0;
        $overrideFailToPass = 0;
        $overridePassToFail = 0;
        $overrideNoChange = 0;

        foreach ($scoredAttempts as $row) {
            $key = $row->training_class_id.':'.$row->student_user_id;
            $skillsScore = isset($skillsScores[$key]) ? (int) $skillsScores[$key]->skills_score : null;
            $effective = $this->effectiveScore->resolve((float) $row->score, $skillsScore, (int) $row->passing_score);

            $effective['passed'] ? $passed++ : $failed++;
            $scoreSum += $effective['score'];
            $scoreCount++;

            if ($skillsScore !== null) {
                $rawPassed = (bool) $row->raw_passed;
                if (! $rawPassed && $effective['passed']) {
                    $overrideFailToPass++;
                } elseif ($rawPassed && ! $effective['passed']) {
                    $overridePassToFail++;
                } else {
                    $overrideNoChange++;
                }
            }
        }

        $scoredTotal = $passed + $failed;
        $activeOverrides = $skillsScores->count();

        return [
            'performance' => [
                'attempts_completed' => (int) ($statusCounts['submitted'] ?? 0),
                'attempts_pending' => (int) ($statusCounts['in_progress'] ?? 0),
                'attempts_expired' => (int) ($statusCounts['expired'] ?? 0),
                'passed' => $passed,
                'failed' => $failed,
                'scored_total' => $scoredTotal,
                'pass_rate' => $scoredTotal > 0 ? round($passed / $scoredTotal * 100, 1) : null,
                'average_effective_score' => $scoreCount > 0 ? round($scoreSum / $scoreCount, 1) : null,
            ],
            'overrides' => [
                'active' => $activeOverrides,
                'fail_to_pass' => $overrideFailToPass,
                'pass_to_fail' => $overridePassToFail,
                'no_change' => $overrideNoChange,
                'not_yet_scored' => max(0, $activeOverrides - $overrideFailToPass - $overridePassToFail - $overrideNoChange),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function certificateOverview(): array
    {
        $now = now()->format('Y-m-d H:i:s');
        $soon = now()->addDays(30)->format('Y-m-d H:i:s');

        $row = DB::table('certificates')->selectRaw(
            "SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) as issued, ".
            "SUM(CASE WHEN status = 'revoked' THEN 1 ELSE 0 END) as revoked, ".
            "SUM(CASE WHEN status = 'issued' AND expires_at IS NOT NULL AND expires_at < ? THEN 1 ELSE 0 END) as expired, ".
            "SUM(CASE WHEN status = 'issued' AND expires_at IS NOT NULL AND expires_at >= ? AND expires_at <= ? THEN 1 ELSE 0 END) as expiring_soon, ".
            "SUM(CASE WHEN status = 'issued' AND (expires_at IS NULL OR expires_at >= ?) THEN 1 ELSE 0 END) as currently_valid",
            [$now, $now, $soon, $now]
        )->first();

        return [
            'issued' => (int) ($row->issued ?? 0),
            'revoked' => (int) ($row->revoked ?? 0),
            'currently_valid' => (int) ($row->currently_valid ?? 0),
            'expired' => (int) ($row->expired ?? 0),
            'expiring_soon' => (int) ($row->expiring_soon ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function staffOverview(): array
    {
        $roleIds = DB::table('roles')->whereIn('key', [Role::PROCTOR, Role::INSTRUCTOR])->pluck('id', 'key');
        $proctorRoleId = $roleIds[Role::PROCTOR] ?? null;
        $instructorRoleId = $roleIds[Role::INSTRUCTOR] ?? null;

        $activeProctors = $proctorRoleId ? $this->activeStaffQuery($proctorRoleId)->count() : 0;
        $activeInstructors = $instructorRoleId ? $this->activeStaffQuery($instructorRoleId)->count() : 0;

        $proctorWorkload = $proctorRoleId
            ? $this->activeStaffQuery($proctorRoleId)
                ->withCount(['proctoredClasses' => fn ($query) => $query->whereIn('status', ['planned', 'active'])])
                ->orderByDesc('proctored_classes_count')->limit(5)->get()
                ->map(fn (User $user): array => ['name' => $user->display_name, 'count' => $user->proctored_classes_count])->values()->all()
            : [];

        $instructorWorkload = $instructorRoleId
            ? $this->activeStaffQuery($instructorRoleId)
                ->withCount(['instructedClasses' => fn ($query) => $query->whereIn('status', ['planned', 'active'])])
                ->orderByDesc('instructed_classes_count')->limit(5)->get()
                ->map(fn (User $user): array => ['name' => $user->display_name, 'count' => $user->instructed_classes_count])->values()->all()
            : [];

        return [
            'active_proctors' => $activeProctors,
            'active_instructors' => $activeInstructors,
            'proctor_workload' => $proctorWorkload,
            'instructor_workload' => $instructorWorkload,
        ];
    }

    private function activeStaffQuery(int $roleId)
    {
        return User::query()->with('profile')
            ->where('current_role_id', $roleId)
            ->where('status', 'active')->whereNull('archived_at');
    }

    /** @return array<string, mixed> */
    private function upcomingClasses(): array
    {
        $ongoing = TrainingClass::query()
            ->where('status', 'active')
            ->with(['course', 'proctor.profile', 'instructor.profile'])
            ->withCount('enrollments')
            ->orderBy('starts_at')
            ->limit(5)->get();

        $upcoming = TrainingClass::query()
            ->where('status', 'planned')
            ->with(['course', 'proctor.profile', 'instructor.profile'])
            ->withCount('enrollments')
            ->orderByRaw('starts_at IS NULL')
            ->orderBy('starts_at')
            ->limit(5)->get();

        return [
            'ongoing' => $ongoing->map(fn (TrainingClass $class): array => $this->classSummary($class))->values()->all(),
            'upcoming' => $upcoming->map(fn (TrainingClass $class): array => $this->classSummary($class))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function classSummary(TrainingClass $class): array
    {
        return [
            'class_number' => $class->class_number,
            'course' => $class->course?->name ?: 'Not assigned',
            'starts_at' => $class->starts_at,
            'status' => $class->status->value,
            'status_label' => $class->status->label(),
            'proctor' => $class->proctor?->display_name ?: 'Unassigned',
            'instructor' => $class->instructor?->display_name ?: 'Unassigned',
            'enrollment_count' => $class->enrollments_count,
            'url' => route('admin.classes.show', $class),
        ];
    }

    /**
     * @param  array<string, mixed>  $classStatus
     * @param  array<string, mixed>  $subjects
     * @return array<int, array<string, mixed>>
     */
    private function attentionItems(array $classStatus, array $subjects): array
    {
        $items = [];

        if ($classStatus['legacy_unassigned'] > 0) {
            $items[] = [
                'label' => 'Classes missing a Proctor or Instructor',
                'count' => $classStatus['legacy_unassigned'],
                'url' => route('admin.classes.index'),
            ];
        }

        $noStudents = TrainingClass::query()->whereIn('status', ['planned', 'active'])->doesntHave('enrollments')->count();
        if ($noStudents > 0) {
            $items[] = [
                'label' => 'Active/planned Classes with no enrolled students',
                'count' => $noStudents,
                'url' => route('admin.classes.index'),
            ];
        }

        $noExamSchedule = TrainingClass::query()->whereIn('status', ['planned', 'active'])->doesntHave('examSchedules')->count();
        if ($noExamSchedule > 0) {
            $items[] = [
                'label' => 'Active/planned Classes with no exam schedule',
                'count' => $noExamSchedule,
                'url' => route('admin.classes.index'),
            ];
        }

        $stuckAttempts = DB::table('exam_attempts')->where('status', 'in_progress')->where('expires_at', '<', now())->count();
        if ($stuckAttempts > 0) {
            $items[] = [
                'label' => 'Exam attempts past their expiry but still in progress',
                'count' => $stuckAttempts,
                'url' => null,
            ];
        }

        if ($subjects['courses_without_exam'] > 0) {
            $items[] = [
                'label' => 'Active Subjects with no exam configured',
                'count' => $subjects['courses_without_exam'],
                'url' => route('admin.subjects.index'),
            ];
        }

        return $items;
    }

    /** @return array<int, array<string, mixed>> */
    private function recentActivity(): array
    {
        return AuditEvent::query()
            ->with('actor.profile')
            ->latest('occurred_at')
            ->limit(8)
            ->get()
            ->map(fn (AuditEvent $event): array => [
                'label' => $this->activityLabel($event->action),
                'actor' => $event->actor?->display_name ?: 'System',
                'subject_type' => $event->subject_type ? class_basename($event->subject_type) : null,
                'occurred_at' => $event->occurred_at,
            ])->values()->all();
    }

    private function activityLabel(string $action): string
    {
        return match ($action) {
            'class.created' => 'Class created',
            'class.updated' => 'Class updated',
            'class.cancelled' => 'Class cancelled',
            'enrollment.created' => 'Student enrolled',
            'enrollment.withdrawn' => 'Student withdrawn',
            'enrollment.skills_score_updated' => 'Skills Score updated',
            'certificate.issued' => 'Certificate issued',
            'certificate.revoked' => 'Certificate revoked',
            'exam_attempt.released' => 'Exam attempt released',
            'user.created', 'student.created' => 'User created',
            'user.updated' => 'User updated',
            'user.role_changed' => 'User role changed',
            default => str($action)->replace(['.', '_'], ' ')->headline()->toString(),
        };
    }

    /**
     * @param  array<string, mixed>  $classStatus
     * @param  array<string, mixed>  $enrollment
     * @param  array<string, mixed>  $examPerformance
     * @param  array<string, mixed>  $certificates
     * @param  array<string, mixed>  $staff
     * @return array<int, array<string, mixed>>
     */
    private function kpis(array $classStatus, array $enrollment, array $examPerformance, array $certificates, array $staff): array
    {
        $ongoing = (int) ($classStatus['counts']['active'] ?? 0);

        return [
            [
                'label' => 'Classes',
                'value' => $classStatus['total'],
                'caption' => $ongoing.' currently ongoing',
                'url' => route('admin.classes.index'),
            ],
            [
                'label' => 'Enrollments',
                'value' => $enrollment['total'],
                'caption' => number_format($enrollment['unique_students']).' unique students',
            ],
            [
                'label' => 'Exam Attempts',
                'value' => $examPerformance['attempts_completed'],
                'caption' => $examPerformance['attempts_pending'].' pending / in progress',
            ],
            [
                'label' => 'Pass Rate',
                'value' => $examPerformance['pass_rate'] !== null ? number_format($examPerformance['pass_rate'], 1).'%' : '—',
                'caption' => 'Based on effective score ('.$examPerformance['scored_total'].' scored)',
            ],
            [
                'label' => 'Certificates',
                'value' => $certificates['issued'],
                'caption' => $certificates['currently_valid'].' currently valid',
                'url' => route('admin.certificates.index'),
            ],
            [
                'label' => 'Active Staff',
                'value' => $staff['active_proctors'] + $staff['active_instructors'],
                'caption' => $staff['active_proctors'].' proctors, '.$staff['active_instructors'].' instructors',
            ],
        ];
    }
}
