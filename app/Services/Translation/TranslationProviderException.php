<?php

namespace App\Services\Translation;

use RuntimeException;

/**
 * Thrown for a provider-level failure that affects an entire call (provider
 * unreachable, malformed response, misconfiguration) - as opposed to a
 * single item failing within an otherwise-successful batch, which is
 * represented per-item via TranslationResult::failure() instead.
 */
class TranslationProviderException extends RuntimeException {}
