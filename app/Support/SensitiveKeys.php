<?php

namespace App\Support;

/**
 * Single source of truth for the array keys that must never surface in an
 * audit trail. Shared by AuditRecorder (removes them before a state is ever
 * persisted) and SystemLogService (masks them again at display time, as a
 * safety net for records written before a key was added here).
 */
final class SensitiveKeys
{
    private const NAMES = [
        'password',
        'password_confirmation',
        'password_ciphertext',
        'remember_token',
        'correct_answer',
        'correct_answer_text',
        'correct_answer_boolean',
        'correct_answer_image_path',
        'answer_key',
        'secret',
        'token',
        'control_id',
    ];

    public static function isSensitive(string $key): bool
    {
        return in_array(strtolower($key), self::NAMES, true);
    }

    /**
     * Recursively removes sensitive keys from an array (used where the key
     * itself must never reach storage).
     */
    public static function scrub(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && self::isSensitive($key)) {
                continue;
            }
            $result[$key] = self::scrub($item);
        }

        return $result;
    }

    /**
     * Recursively masks sensitive keys in an array (used for display, where
     * keeping the key visible but hiding its value is more legible than
     * making it disappear).
     */
    public static function mask(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            $result[$key] = is_string($key) && self::isSensitive($key) ? '[REDACTED]' : self::mask($item);
        }

        return $result;
    }
}
