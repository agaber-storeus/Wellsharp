<?php

namespace App\Actions\Classes;

use App\Actions\Certificates\IssueCertificateAction;
use App\Models\Enrollment;
use App\Services\AuditRecorder;
use App\Services\EffectiveScoreService;
use Illuminate\Support\Facades\DB;

/**
 * Skills Score is a manual override of a trainee's final/effective percentage
 * (see EffectiveScoreService), not an independent informational value - so
 * setting or clearing it can change whether the trainee's Class result is a
 * pass, which in turn can change certificate eligibility. This action is the
 * one place that writes `enrollments.skills_score`, so that reconciliation
 * always happens through the real IssueCertificateAction (which re-evaluates
 * eligibility and is idempotent per attempt) rather than an ad-hoc write that
 * could leave a certificate out of sync with the override that produced it.
 *
 * Reconciliation only ever issues a certificate that is now newly eligible
 * (raw exam failed, override raised it above the passing score). It never
 * revokes or deletes one: certificates are immutable snapshots once issued
 * (BR-031) and this domain has no implemented revocation workflow (BR-033),
 * so an override that drops a previously-passing trainee below the passing
 * score leaves any already-issued certificate exactly as it was.
 */
class UpdateEnrollmentSkillsScoreAction
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly EffectiveScoreService $effectiveScore,
        private readonly IssueCertificateAction $issuer,
    ) {}

    public function execute(Enrollment $enrollment, ?int $skillsScore): Enrollment
    {
        return DB::transaction(function () use ($enrollment, $skillsScore): Enrollment {
            $locked = Enrollment::query()->lockForUpdate()->findOrFail($enrollment->getKey());
            $before = ['skills_score' => $locked->skills_score];
            $locked->update(['skills_score' => $skillsScore]);
            $this->audit->record('enrollment.skills_score_updated', $locked, $before, ['skills_score' => $skillsScore]);

            $attempt = $this->effectiveScore->latestAttemptFor($locked);
            if ($attempt) {
                $this->issuer->execute($attempt);
            }

            return $locked->fresh();
        });
    }
}
