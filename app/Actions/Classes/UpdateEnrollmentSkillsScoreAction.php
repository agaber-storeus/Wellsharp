<?php

namespace App\Actions\Classes;

use App\Models\Enrollment;
use App\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;

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
            $before = ['skills_score' => $locked->skills_score];
            $locked->update(['skills_score' => $skillsScore]);
            $this->audit->record('enrollment.skills_score_updated', $locked, $before, ['skills_score' => $skillsScore]);

            return $locked->fresh();
        });
    }
}
