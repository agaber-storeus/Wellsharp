<?php

namespace App\Http\Controllers\Operational;

use App\Actions\Exams\ControlOperationalExamAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\ControlExamRequest;
use App\Models\TrainingClass;
use App\Services\ProctorIdVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamControlController extends Controller
{
    public function verifyProctorId(Request $request, ProctorIdVerifier $proctorIds): JsonResponse
    {
        $data = $request->validate(['proctor_id' => ['required', 'string', 'max:32']], [
            'proctor_id.required' => "Proctor's ID is required.",
        ]);
        $proctor = $proctorIds->findActiveProctor($data['proctor_id']);

        if (! $proctor) {
            return response()->json(['message' => "The provided Proctor's ID does not belong to an active Proctor."], 422);
        }

        return response()->json(['message' => "Proctor's ID verified.", 'proctor_name' => $proctor->display_name]);
    }

    public function control(ControlExamRequest $request, TrainingClass $trainingClass, ControlOperationalExamAction $action): JsonResponse
    {
        $this->authorize('control', $trainingClass);
        $data = $request->validated();
        $result = $action->executeManual($trainingClass, $data['action'], $request->user(), $data['proctor_id'] ?? null);

        return response()->json([
            'message' => $data['action'] === 'start' ? 'Class and linked exam started.' : 'Class and linked exam ended.',
            'class' => [
                'id' => $result['class']->public_id,
                'status' => $result['class']->status->value,
                'starts_at' => $result['class']->starts_at?->toIso8601String(),
                'ends_at' => $result['class']->ends_at?->toIso8601String(),
            ],
            'proctor_name' => $result['proctor_name'],
            'schedules_controlled' => $result['schedules_controlled'],
        ]);
    }
}
