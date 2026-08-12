<?php

namespace App\Actions\Classes;

use App\Enums\ClassStatus;
use App\Models\TrainingClass;
use App\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelTrainingClassAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(TrainingClass $trainingClass): TrainingClass
    {
        return DB::transaction(function () use ($trainingClass): TrainingClass {
            $locked = TrainingClass::query()->lockForUpdate()->findOrFail($trainingClass->getKey());

            if (in_array($locked->status, [ClassStatus::Completed, ClassStatus::Cancelled], true)) {
                throw ValidationException::withMessages(['class' => 'Completed or cancelled classes cannot be cancelled.']);
            }

            $before = $locked->toArray();
            $locked->forceFill(['status' => ClassStatus::Cancelled])->save();
            $this->audit->record('class.cancelled', $locked, $before, $locked->fresh()->toArray());

            return $locked->fresh();
        });
    }
}
