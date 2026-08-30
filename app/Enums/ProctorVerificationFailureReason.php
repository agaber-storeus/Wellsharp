<?php

namespace App\Enums;

/**
 * Every reason ProctorIdVerifier::verify() can actually distinguish for a
 * failed lookup - kept in lockstep with that method so the audit trail
 * never reports a reason the code did not really detect.
 */
enum ProctorVerificationFailureReason: string
{
    case MissingProctorId = 'missing_proctor_id';
    case UserNotFound = 'user_not_found';
    case NotAProctor = 'not_a_proctor';
    case ProctorInactive = 'proctor_inactive';

    /** The pipeline stage this reason was detected at - see class.control_attempt.failed / class.proctor_verification.failed. */
    public function stage(): string
    {
        return $this === self::MissingProctorId ? 'validation' : 'verification';
    }

    public function label(): string
    {
        return match ($this) {
            self::MissingProctorId => "No Proctor's ID was entered",
            self::UserNotFound => "The entered Proctor's ID does not match any credential",
            self::NotAProctor => "The credential does not belong to a current Proctor",
            self::ProctorInactive => 'The Proctor is not active',
        };
    }
}
