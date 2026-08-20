<?php

namespace App\Services\Generation;

use RuntimeException;

/**
 * Thrown when a generator cannot find a unique candidate within its bounded
 * retry budget. Signals a systemic problem (namespace exhaustion, a broken
 * uniqueness check) rather than being caught and silently retried further.
 */
class GenerationRetryExhaustedException extends RuntimeException {}
