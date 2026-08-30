<?php

namespace App\Enums;

/**
 * Reasons a Class Start/End control attempt can be rejected once Proctor
 * verification is no longer the concern - either the actor isn't allowed to
 * control this specific Class, or the Class's current status doesn't permit
 * the requested operation. Kept in lockstep with
 * ControlOperationalExamAction and TrainingClassPolicy::control().
 */
enum ClassControlFailureReason: string
{
    case ClassAlreadyActive = 'class_already_active';
    case ClassAlreadyCompleted = 'class_already_completed';
    case ClassCancelled = 'class_cancelled';
    case ClassNotStarted = 'class_not_started';
    case NotAssignedToClass = 'not_assigned_to_class';
    case UnsupportedRole = 'unsupported_role';

    public function stage(): string
    {
        return match ($this) {
            self::NotAssignedToClass, self::UnsupportedRole => 'authorization',
            default => 'class_state',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ClassAlreadyActive => 'Class is already active',
            self::ClassAlreadyCompleted => 'Class is already completed',
            self::ClassCancelled => 'Class has been cancelled',
            self::ClassNotStarted => 'Class has not been started yet',
            self::NotAssignedToClass => 'Actor is not assigned to this Class',
            self::UnsupportedRole => 'Actor role cannot control a Class',
        };
    }
}
