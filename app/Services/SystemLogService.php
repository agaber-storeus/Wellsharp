<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\LoginEvent;
use App\Models\Question;
use App\Models\Role;
use App\Models\TrainingClass;
use App\Models\TrainingProvider;
use App\Models\User;
use App\Support\SensitiveKeys;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SystemLogService
{
    public const PER_PAGE = 25;

    private const CATEGORIES = [
        'authentication' => 'Authentication',
        'security' => 'Security',
        'users' => 'Users',
        'courses' => 'Courses',
        'questions' => 'Questions',
        'exams' => 'Exams',
        'schedules' => 'Schedules',
        'classes' => 'Classes',
        'groups' => 'Groups',
        'enrollments' => 'Enrollments',
        'assessments' => 'Assessments',
        'scores' => 'Scores',
        'certificates' => 'Certificates',
        'system' => 'System',
    ];

    private const ACTIONS = [
        'user.created' => ['category' => 'users', 'label' => 'User created'],
        'student.created' => ['category' => 'users', 'label' => 'Student created'],
        'user.updated' => ['category' => 'users', 'label' => 'User updated'],
        'student.updated' => ['category' => 'users', 'label' => 'Student updated'],
        'user.profile_updated' => ['category' => 'users', 'label' => 'User profile updated'],
        'user.role_changed' => ['category' => 'users', 'label' => 'User role changed'],
        'user.disabled' => ['category' => 'users', 'label' => 'User disabled'],
        'user.status_updated' => ['category' => 'users', 'label' => 'User status updated'],
        'user.archived' => ['category' => 'users', 'label' => 'User archived'],
        'user.unarchived' => ['category' => 'users', 'label' => 'User unarchived'],
        'student.password_viewed' => ['category' => 'security', 'label' => 'Student password viewed', 'severity' => 'warning'],
        'student_passwords.class_roster_viewed' => ['category' => 'security', 'label' => 'Class roster passwords viewed', 'severity' => 'warning'],
        'class.proctor_verification.succeeded' => ['category' => 'security', 'label' => 'Proctor verification succeeded'],
        'class.proctor_verification.failed' => ['category' => 'security', 'label' => 'Proctor verification failed', 'result' => 'failed', 'severity' => 'warning'],
        'class.control_attempt.failed' => ['category' => 'security', 'label' => 'Class control attempt failed', 'result' => 'failed', 'severity' => 'warning'],
        'training_provider.created' => ['category' => 'courses', 'label' => 'Training provider created'],
        'training_provider.updated' => ['category' => 'courses', 'label' => 'Training provider updated'],
        'training_provider.status_updated' => ['category' => 'courses', 'label' => 'Training provider status updated'],
        'training_provider.archived' => ['category' => 'courses', 'label' => 'Training provider archived'],
        'training_provider.unarchived' => ['category' => 'courses', 'label' => 'Training provider unarchived'],
        'course.created' => ['category' => 'courses', 'label' => 'Course created'],
        'course.updated' => ['category' => 'courses', 'label' => 'Course updated'],
        'course.archived' => ['category' => 'courses', 'label' => 'Course archived'],
        'course.unarchived' => ['category' => 'courses', 'label' => 'Course unarchived'],
        'question.created' => ['category' => 'questions', 'label' => 'Question created'],
        'question.updated' => ['category' => 'questions', 'label' => 'Question updated'],
        'question.archived' => ['category' => 'questions', 'label' => 'Question archived'],
        'question.unarchived' => ['category' => 'questions', 'label' => 'Question unarchived'],
        'questions.imported' => ['category' => 'questions', 'label' => 'Questions imported'],
        'exam.created' => ['category' => 'exams', 'label' => 'Exam created'],
        'exam.updated' => ['category' => 'exams', 'label' => 'Exam updated'],
        'exam.questions_updated' => ['category' => 'exams', 'label' => 'Exam questions updated'],
        'exam.archived' => ['category' => 'exams', 'label' => 'Exam archived'],
        'exam.unarchived' => ['category' => 'exams', 'label' => 'Exam unarchived'],
        'exam_schedule.created' => ['category' => 'schedules', 'label' => 'Exam schedule created'],
        'exam_schedule.updated' => ['category' => 'schedules', 'label' => 'Exam schedule updated'],
        'exam_schedule.cancelled' => ['category' => 'schedules', 'label' => 'Exam schedule cancelled'],
        'exam_schedule.manual_start' => ['category' => 'schedules', 'label' => 'Exam schedule started manually'],
        'exam_schedule.manual_end' => ['category' => 'schedules', 'label' => 'Exam schedule ended manually'],
        'exam_schedule.automatic_start' => ['category' => 'schedules', 'label' => 'Exam schedule started automatically', 'result' => 'system'],
        'exam_schedule.automatic_end' => ['category' => 'schedules', 'label' => 'Exam schedule ended automatically', 'result' => 'system'],
        'class.created' => ['category' => 'classes', 'label' => 'Class created'],
        'class.updated' => ['category' => 'classes', 'label' => 'Class updated'],
        'class.cancelled' => ['category' => 'classes', 'label' => 'Class cancelled'],
        'class.manual_start' => ['category' => 'classes', 'label' => 'Class started manually'],
        'class.manual_end' => ['category' => 'classes', 'label' => 'Class ended manually'],
        'class.automatic_start' => ['category' => 'classes', 'label' => 'Class started automatically', 'result' => 'system'],
        'class.automatic_end' => ['category' => 'classes', 'label' => 'Class ended automatically', 'result' => 'system'],
        'enrollment.created' => ['category' => 'enrollments', 'label' => 'Student enrolled'],
        'enrollment.withdrawn' => ['category' => 'enrollments', 'label' => 'Student withdrawn'],
        'group.created' => ['category' => 'groups', 'label' => 'Group created'],
        'group.updated' => ['category' => 'groups', 'label' => 'Group updated'],
        'group.unarchived' => ['category' => 'groups', 'label' => 'Group unarchived'],
        'group.student_added' => ['category' => 'groups', 'label' => 'Student added to group'],
        'group.student_removed' => ['category' => 'groups', 'label' => 'Student removed from group'],
        'exam_attempt.released' => ['category' => 'assessments', 'label' => 'Exam attempt released'],
        'enrollment.skills_score_updated' => ['category' => 'scores', 'label' => 'Skills Score updated'],
        'certificate.issued' => ['category' => 'certificates', 'label' => 'Certificate issued'],
        'certificate.revoked' => ['category' => 'certificates', 'label' => 'Certificate revoked', 'severity' => 'warning'],
    ];

    private const SUBJECT_TYPES = [
        User::class,
        TrainingProvider::class,
        Course::class,
        Question::class,
        Exam::class,
        ExamSchedule::class,
        TrainingClass::class,
        Group::class,
        Enrollment::class,
        ExamAttempt::class,
        Certificate::class,
    ];

    /** @return array<string, string> */
    public static function categories(): array
    {
        return self::CATEGORIES;
    }

    /** @return array<string, string> */
    public static function roles(): array
    {
        return [
            Role::ADMIN => 'Admin',
            Role::PROCTOR => 'Proctor',
            Role::INSTRUCTOR => 'Instructor',
            Role::STUDENT => 'Student',
        ];
    }

    /** @return array<string, string> */
    public function actionOptions(): array
    {
        $auditActions = AuditEvent::query()->select('action')->distinct()->orderBy('action')->pluck('action');
        $loginActions = LoginEvent::query()->select('outcome')->distinct()->orderBy('outcome')->pluck('outcome')->map(fn (string $outcome): string => 'login.'.$outcome);

        return $auditActions
            ->merge($loginActions)
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $action): array => [$action => $this->definition($action)['label']])
            ->all();
    }

    /** @return array<string, string> */
    public function subjectTypeOptions(): array
    {
        return AuditEvent::query()
            ->whereNotNull('subject_type')
            ->select('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->mapWithKeys(fn (string $type): array => [$type => class_basename($type)])
            ->all();
    }

    /**
     * Bounded to users who have actually acted on a business/security event -
     * never the full user table, which for this application's actor set
     * (Admin/Proctor/Instructor actions) is already a small list, unlike the
     * Student-heavy `users` table as a whole.
     *
     * @return Collection<int, User>
     */
    public function actorOptions(): Collection
    {
        $ids = AuditEvent::query()->whereNotNull('actor_user_id')->select('actor_user_id')->distinct()->pluck('actor_user_id');

        return User::query()
            ->with(['profile', 'currentRole'])
            ->whereIn('id', $ids)
            ->orderBy('wellsharp_id')
            ->get();
    }

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function paginate(array $filters, int $page = 1): LengthAwarePaginator
    {
        $audit = $this->auditQuery($filters);
        $login = $this->loginQuery($filters);
        $union = $audit->unionAll($login);

        $paginator = DB::query()
            ->fromSub($union, 'system_logs')
            ->orderByDesc('occurred_at')
            ->orderByDesc('source_order')
            ->orderByDesc('source_id')
            ->paginate(self::PER_PAGE, ['*'], 'page', max(1, $page))
            ->withQueryString();

        $actors = $this->actorsFor($paginator->getCollection());
        $paginator->setCollection($paginator->getCollection()->map(fn (object $row): array => $this->rowEntry($row, $actors->get((int) $row->actor_id))));

        return $paginator;
    }

    /** @return array<string, mixed> */
    public function find(string $source, string $publicId): array
    {
        if ($source === 'audit') {
            $event = AuditEvent::query()
                ->with(['actor.profile', 'actor.currentRole'])
                ->where('public_id', $publicId)
                ->firstOrFail();

            return $this->auditEntry($event);
        }

        abort_unless($source === 'login', 404);
        $event = LoginEvent::query()
            ->with(['user.profile', 'user.currentRole'])
            ->where('public_id', $publicId)
            ->firstOrFail();

        return $this->loginEntry($event);
    }

    private function auditQuery(array $filters): Builder
    {
        $query = AuditEvent::query()
            ->select([
                DB::raw("'audit' as source"),
                'audit_events.id as source_id',
                'audit_events.public_id',
                'audit_events.actor_user_id as actor_id',
                'audit_events.action as raw_action',
                'audit_events.subject_type',
                'audit_events.subject_id',
                'audit_events.reason',
                'audit_events.correlation_id',
                'audit_events.ip_address',
                'audit_events.user_agent',
                'audit_events.occurred_at',
                DB::raw('1 as source_order'),
            ]);

        $this->applyFilters($query, $filters, 'audit');

        return $query;
    }

    private function loginQuery(array $filters): Builder
    {
        $query = LoginEvent::query()
            ->select([
                DB::raw("'login' as source"),
                'login_events.id as source_id',
                'login_events.public_id',
                'login_events.user_id as actor_id',
                'login_events.outcome as raw_action',
                DB::raw('NULL as subject_type'),
                DB::raw('NULL as subject_id'),
                DB::raw('NULL as reason'),
                'login_events.correlation_id',
                'login_events.ip_address',
                'login_events.user_agent',
                'login_events.occurred_at',
                DB::raw('0 as source_order'),
            ]);

        $this->applyFilters($query, $filters, 'login');

        return $query;
    }

    private function applyFilters(Builder $query, array $filters, string $source): void
    {
        $column = $source === 'audit' ? 'audit_events.occurred_at' : 'login_events.occurred_at';
        $query->when(($filters['date_from'] ?? '') !== '', fn (Builder $query): Builder => $query->where($column, '>=', $this->dateBoundary($filters['date_from'], false)))
            ->when(($filters['date_to'] ?? '') !== '', fn (Builder $query): Builder => $query->where($column, '<=', $this->dateBoundary($filters['date_to'], true)))
            ->when(($filters['actor_id'] ?? '') !== '', fn (Builder $query): Builder => $query->where($source === 'audit' ? 'audit_events.actor_user_id' : 'login_events.user_id', (int) $filters['actor_id']))
            ->when(($filters['actor_role'] ?? '') !== '', function (Builder $query) use ($filters, $source): void {
                $actorColumn = $source === 'audit' ? 'audit_events.actor_user_id' : 'login_events.user_id';
                $query->whereIn($actorColumn, User::query()->whereHas('currentRole', fn (Builder $roleQuery): Builder => $roleQuery->where('key', $filters['actor_role']))->select('users.id'));
            });

        if (($filters['action'] ?? '') !== '') {
            if ($source === 'login') {
                $query->where('login_events.outcome', substr((string) $filters['action'], 6));
            } else {
                $query->where('audit_events.action', $filters['action']);
            }
        }

        if (($filters['category'] ?? '') !== '') {
            if ($source === 'login') {
                $query->when($filters['category'] !== 'authentication', fn (Builder $query): Builder => $query->whereRaw('1 = 0'));
            } else {
                $actions = collect(self::ACTIONS)
                    ->filter(fn (array $definition): bool => $definition['category'] === $filters['category'])
                    ->keys();
                $query->whereIn('audit_events.action', $actions->all());
            }
        }

        if (($filters['subject_type'] ?? '') !== '') {
            $query->when($source === 'audit', fn (Builder $query): Builder => $query->where('audit_events.subject_type', $filters['subject_type']))
                ->when($source === 'login', fn (Builder $query): Builder => $query->whereRaw('1 = 0'));
        }

        if (($filters['correlation_id'] ?? '') !== '') {
            $query->where($source === 'audit' ? 'audit_events.correlation_id' : 'login_events.correlation_id', $filters['correlation_id']);
        }

        if (($filters['result'] ?? '') !== '') {
            if ($source === 'login') {
                if ($filters['result'] === 'system') {
                    $query->whereRaw('1 = 0');
                } elseif ($filters['result'] === 'success') {
                    $query->whereIn('login_events.outcome', ['success', 'logout']);
                } else {
                    $query->whereIn('login_events.outcome', ['invalid_credentials', 'inactive']);
                }
            } else {
                // Most actions never carry an explicit `result` (see definition()'s
                // default of "success"), so the finite "failed"/"system" action
                // lists are the source of truth and "success" is everything else -
                // not a lookup of actions explicitly tagged success, which would
                // wrongly exclude the vast majority of ordinary business actions.
                $failed = $this->actionsWithResult('failed');
                $system = $this->actionsWithResult('system');

                if ($filters['result'] === 'failed') {
                    $query->whereIn('audit_events.action', $failed);
                } elseif ($filters['result'] === 'system') {
                    $query->whereIn('audit_events.action', $system);
                } else {
                    $query->whereNotIn('audit_events.action', array_merge($failed, $system));
                }
            }
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $actorIds = User::query()
                ->where('wellsharp_id', 'like', '%'.$search.'%')
                ->orWhereHas('profile', fn (Builder $profile): Builder => $profile->where('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%'))
                ->pluck('users.id');

            $query->where(function (Builder $query) use ($search, $actorIds, $source): void {
                $like = '%'.$search.'%';
                if ($source === 'audit') {
                    $query->where('audit_events.action', 'like', $like)
                        ->orWhere('audit_events.reason', 'like', $like)
                        ->orWhere('audit_events.correlation_id', 'like', $like)
                        ->orWhere('audit_events.subject_id', 'like', $like);
                    if ($actorIds->isNotEmpty()) {
                        $query->orWhereIn('audit_events.actor_user_id', $actorIds->all());
                    }
                } else {
                    $query->where('login_events.wellsharp_id', 'like', $like)
                        ->orWhere('login_events.outcome', 'like', $like)
                        ->orWhere('login_events.correlation_id', 'like', $like);
                    if ($actorIds->isNotEmpty()) {
                        $query->orWhereIn('login_events.user_id', $actorIds->all());
                    }
                }
            });
        }
    }

    private function dateBoundary(string $date, bool $end): Carbon
    {
        $boundary = Carbon::createFromFormat('Y-m-d', $date, config('app.timezone', 'UTC'));

        return ($end ? $boundary->endOfDay() : $boundary->startOfDay())->utc();
    }

    /** @param Collection<int, object> $rows */
    private function actorsFor(Collection $rows): Collection
    {
        $ids = $rows->pluck('actor_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return User::query()->with(['profile', 'currentRole'])->whereIn('id', $ids)->get()->keyBy('id');
    }

    /** @return array<string, mixed> */
    private function rowEntry(object $row, ?User $actor): array
    {
        $action = $row->source === 'login' ? 'login.'.(string) $row->raw_action : (string) $row->raw_action;
        $definition = $this->definition($action);

        return [
            'id' => $row->source.':'.$row->public_id,
            'source' => $row->source,
            'public_id' => $row->public_id,
            'action' => $action,
            'label' => $definition['label'],
            'category' => $definition['category'],
            'category_label' => self::CATEGORIES[$definition['category']] ?? 'System',
            'actor' => $actor?->display_name ?: ($row->source === 'login' ? ((string) ($row->raw_action === 'invalid_credentials' ? 'Unknown user' : 'System')) : 'System'),
            'actor_role' => $actor?->currentRole?->name,
            'subject' => $this->subjectReference($row->subject_type, $row->subject_id, $row->source),
            'result' => $definition['result'],
            'severity' => $definition['severity'] ?? ($definition['result'] === 'failed' ? 'warning' : 'info'),
            'description' => $definition['label'],
            'reason' => $row->reason,
            'correlation_id' => $row->correlation_id,
            'occurred_at' => Carbon::parse($row->occurred_at),
            'detail_url' => route('admin.system-logs.show', [$row->source, $row->public_id]),
        ];
    }

    /** @return array<string, mixed> */
    private function auditEntry(AuditEvent $event): array
    {
        $entry = $this->rowEntry((object) [
            'source' => 'audit',
            'public_id' => $event->public_id,
            'raw_action' => $event->action,
            'actor_id' => $event->actor_user_id,
            'subject_type' => $event->subject_type,
            'subject_id' => $event->subject_id,
            'reason' => $event->reason,
            'correlation_id' => $event->correlation_id,
            'occurred_at' => $event->occurred_at,
        ], $event->actor);

        return $entry + [
            'ip_address' => $event->ip_address,
            'user_agent' => $event->user_agent,
            'before_state' => $this->sanitizeState($event->before_state),
            'after_state' => $this->sanitizeState($event->after_state),
            'subject_detail' => $this->resolveSubjectDetail($event),
        ];
    }

    /** @return array<string, mixed> */
    private function loginEntry(LoginEvent $event): array
    {
        return $this->rowEntry((object) [
            'source' => 'login',
            'public_id' => $event->public_id,
            'raw_action' => $event->outcome,
            'actor_id' => $event->user_id,
            'subject_type' => null,
            'subject_id' => null,
            'reason' => null,
            'correlation_id' => $event->correlation_id,
            'occurred_at' => $event->occurred_at,
        ], $event->user) + [
            'ip_address' => $event->ip_address,
            'user_agent' => $event->user_agent,
            'before_state' => null,
            'after_state' => ['wellsharp_id' => $event->wellsharp_id, 'outcome' => $event->outcome],
            'subject_detail' => 'Authentication event',
        ];
    }

    /** @return array{category: string, label: string, result: string, severity?: string} */
    private function definition(string $action): array
    {
        if (isset(self::ACTIONS[$action])) {
            return self::ACTIONS[$action] + ['result' => 'success'];
        }

        if (str_starts_with($action, 'login.')) {
            return $this->loginDefinition(substr($action, 6));
        }

        $category = match (true) {
            str_starts_with($action, 'user.'), str_starts_with($action, 'student.') => 'users',
            str_starts_with($action, 'training_provider.'), str_starts_with($action, 'course.') => 'courses',
            str_starts_with($action, 'question'), str_starts_with($action, 'questions.') => 'questions',
            str_starts_with($action, 'exam_schedule.') => 'schedules',
            str_starts_with($action, 'exam_attempt.') => 'assessments',
            str_starts_with($action, 'exam.') => 'exams',
            str_starts_with($action, 'class.') => 'classes',
            str_starts_with($action, 'group.') => 'groups',
            str_starts_with($action, 'enrollment.skills') => 'scores',
            str_starts_with($action, 'enrollment.') => 'enrollments',
            str_starts_with($action, 'certificate.') => 'certificates',
            str_contains($action, 'verification'), str_contains($action, 'password') => 'security',
            default => 'system',
        };

        return [
            'category' => $category,
            'label' => str($action)->replace(['.', '_'], ' ')->headline()->toString(),
            'result' => 'success',
        ];
    }

    /** @return array{category: string, label: string, result: string, severity: string} */
    private function loginDefinition(string $outcome): array
    {
        return match ($outcome) {
            'success' => ['category' => 'authentication', 'label' => 'Successful login', 'result' => 'success', 'severity' => 'info'],
            'logout' => ['category' => 'authentication', 'label' => 'User logged out', 'result' => 'success', 'severity' => 'info'],
            'invalid_credentials' => ['category' => 'authentication', 'label' => 'Failed login (invalid credentials)', 'result' => 'failed', 'severity' => 'warning'],
            'inactive' => ['category' => 'authentication', 'label' => 'Failed login (inactive account)', 'result' => 'failed', 'severity' => 'warning'],
            default => ['category' => 'authentication', 'label' => 'Login '.str($outcome)->replace('_', ' ')->headline()->toString(), 'result' => 'failed', 'severity' => 'warning'],
        };
    }

    /** @return array<int, string> */
    private function actionsWithResult(string $result): array
    {
        return collect(self::ACTIONS)
            ->filter(fn (array $definition): bool => ($definition['result'] ?? null) === $result)
            ->keys()
            ->all();
    }

    private function subjectReference(?string $type, ?string $id, string $source): ?string
    {
        if ($source === 'login') {
            return 'Authentication';
        }

        if (! $type && ! $id) {
            return null;
        }

        return class_basename((string) $type).($id !== null ? ' #'.$id : '');
    }

    private function resolveSubjectDetail(AuditEvent $event): ?string
    {
        if (! $event->subject_type || ! $event->subject_id) {
            return null;
        }

        if (! in_array($event->subject_type, self::SUBJECT_TYPES, true)) {
            return class_basename($event->subject_type).' #'.$event->subject_id;
        }

        $model = $event->subject_type::query()->find((int) $event->subject_id);
        if (! $model) {
            return class_basename($event->subject_type).' #'.$event->subject_id.' (no longer available)';
        }

        return match ($event->subject_type) {
            User::class => $model->display_name.' ('.$model->wellsharp_id.')',
            TrainingProvider::class, Course::class, Group::class, Certificate::class => $model->name ?? $model->certificate_number ?? class_basename($event->subject_type),
            TrainingClass::class => $model->class_number,
            Exam::class => $model->name.' ('.$model->code.')',
            Question::class => $model->code ?: class_basename($event->subject_type),
            ExamSchedule::class => $model->exam?->name ?: class_basename($event->subject_type),
            ExamAttempt::class => $model->student?->display_name ?: class_basename($event->subject_type),
            Enrollment::class => $model->student?->display_name ?: class_basename($event->subject_type),
            default => class_basename($event->subject_type),
        };
    }

    private function sanitizeState(mixed $state): mixed
    {
        return SensitiveKeys::mask($state);
    }
}
