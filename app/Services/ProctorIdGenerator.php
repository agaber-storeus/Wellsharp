<?php

namespace App\Services;

use App\Models\ExamControlCredential;
use Illuminate\Support\Str;

class ProctorIdGenerator
{
    public function generate(): string
    {
        do {
            $proctorId = 'PR-'.Str::upper(Str::random(10));
        } while (ExamControlCredential::query()->where('control_id', $proctorId)->exists());

        return $proctorId;
    }
}
