<?php

namespace App\Providers;

use App\Models\AuditEvent;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\Question;
use App\Models\TrainingClass;
use App\Models\TrainingProvider;
use App\Models\User;
use App\Policies\AuditPolicy;
use App\Policies\CoursePolicy;
use App\Policies\EnrollmentPolicy;
use App\Policies\ExamPolicy;
use App\Policies\ExamSchedulePolicy;
use App\Policies\GroupPolicy;
use App\Policies\QuestionPolicy;
use App\Policies\TrainingClassPolicy;
use App\Policies\TrainingProviderPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(TrainingProvider::class, TrainingProviderPolicy::class);
        Gate::policy(Course::class, CoursePolicy::class);
        Gate::policy(TrainingClass::class, TrainingClassPolicy::class);
        Gate::policy(Enrollment::class, EnrollmentPolicy::class);
        Gate::policy(Group::class, GroupPolicy::class);
        Gate::policy(Exam::class, ExamPolicy::class);
        Gate::policy(ExamSchedule::class, ExamSchedulePolicy::class);
        Gate::policy(Question::class, QuestionPolicy::class);
        Gate::policy(AuditEvent::class, AuditPolicy::class);
    }
}
