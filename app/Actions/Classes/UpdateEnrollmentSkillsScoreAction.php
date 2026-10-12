<?php

namespace App\Actions\Classes;

use App\Models\Enrollment;
use App\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Practical / Skills Score is independent from Knowledge Exam results and
 * certificate eligibility.
 */
class UpdateEnrollmentSkillsScoreAction
{
    public function __construct(
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(Enrollment $enrollment, ?int $skillsScore): Enrollment
    {
        return DB::transaction(function () use ($enrollment, $skillsScore): Enrollment {
            $locked = Enrollment::query()->lockForUpdate()->findOrFail($enrollment->getKey());
            if (($locked->trainingClass?->course?->skillsAssessmentApplicable() ?? true) === false) {
                throw ValidationException::withMessages(['skills_score' => 'Skills Assessment is not applicable to this Subject.']);
            }
            $before = ['skills_score' => $locked->skills_score];
            $locked->update(['skills_score' => $skillsScore]);
            $this->audit->record('enrollment.skills_score_updated', $locked, $before, ['skills_score' => $skillsScore]);

            return $locked->fresh();
        });
    }
}
