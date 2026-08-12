<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasPublicUlid
{
    protected static function bootHasPublicUlid(): void
    {
        static::creating(function ($model): void {
            if (blank($model->public_id)) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }
}
