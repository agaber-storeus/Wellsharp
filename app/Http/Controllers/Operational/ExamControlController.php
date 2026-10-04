<?php

namespace App\Http\Controllers\Operational;

use App\Actions\Exams\ControlOperationalExamAction;
use App\Enums\ClassControlFailureReason;
use App\Enums\ProctorVerificationFailureReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\ControlExamRequest;
use App\Models\TrainingClass;
use App\Services\AuditRecorder;
use App\Services\ProctorIdVerifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExamControlController extends Controller
{
    public function verifyProctorId(Request $request, ProctorIdVerifier $proctorIds, AuditRecorder $audit): JsonResponse
    {
        $validator = Validator::make($request->all(), ['proctor_id' => ['required', 'string', 'max:32']], [
            'proctor_id.required' => "Proctor's ID is required.",
        ]);
        $enteredProctorId = (string) $request->input('proctor_id', '');

        if ($validator->fails()) {
            if ($request->user()?->hasRole('instructor') === true) {
                $audit->record(
                    'class.proctor_verification.failed',
                    null,
                    null,
                    $this->proctorIdActivityState('standalone_verify', $enteredProctorId, 'failed', [
                        'failure_stage' => ProctorVerificationFailureReason::MissingProctorId->stage(),
                        'failure_reason' => ProctorVerificationFailureReason::MissingProctorId->value,
                        'control_status' => 'not_applicable',
                    ]),
                    ProctorVerificationFailureReason::MissingProctorId->label(),
                    $request->user()?->getKey(),
                );
            }

            $validator->validate();
        }

        $data = $validator->validated();
        $result = $proctorIds->verify($data['proctor_id']);

        if (! $result->succeeded()) {
            if ($request->user()?->hasRole('instructor') === true) {
                $audit->record(
                    'class.proctor_verification.failed',
                    null,
                    null,
                    $this->proctorIdActivityState('standalone_verify', $enteredProctorId, 'failed', [
                        'failure_stage' => $result->failureReason->stage(),
                        'failure_reason' => $result->failureReason->value,
                        'control_status' => 'not_applicable',
                    ]),
                    $result->failureReason->label(),
                    $request->user()?->getKey(),
                );
            }

            return response()->json(['message' => "The provided Proctor's ID does not belong to an active Proctor."], 422);
        }

        if ($request->user()?->hasRole('instructor') === true) {
            $audit->record(
                'class.proctor_verification.succeeded',
                null,
                null,
                $this->proctorIdActivityState('standalone_verify', $enteredProctorId, 'success', [
                    'control_status' => 'not_applicable',
                    'verified_proctor_user_id' => $result->proctor->getKey(),
                    'verified_proctor_wellsharp_id' => $result->proctor->wellsharp_id,
                    'verified_proctor_display_name' => $result->proctor->display_name,
                ]),
                'Standalone Proctor ID verified',
                $request->user()?->getKey(),
            );
        }

        return response()->json(['message' => "Proctor's ID verified.", 'proctor_name' => $result->proctor->display_name]);
    }

    public function control(ControlExamRequest $request, TrainingClass $trainingClass, ControlOperationalExamAction $action, AuditRecorder $audit): JsonResponse
    {
        try {
            $this->authorize('control', $trainingClass);
        } catch (AuthorizationException $exception) {
            $reason = ClassControlFailureReason::NotAssignedToClass;
            $audit->record(
                'class.control_attempt.failed',
                $trainingClass,
                null,
                $request->user()?->hasRole('instructor') === true
                    ? $this->proctorIdActivityState((string) $request->input('action'), (string) $request->input('proctor_id', ''), 'not_run', [
                        'failure_stage' => $reason->stage(),
                        'failure_reason' => $reason->value,
                        'control_status' => 'failed',
                        'control_failure_stage' => $reason->stage(),
                        'control_failure_reason' => $reason->value,
                        'class_id' => $trainingClass->getKey(),
                        'class_public_id' => $trainingClass->public_id,
                        'class_number' => $trainingClass->class_number,
                    ])
                    : ['operation' => $request->input('action'), 'failure_stage' => $reason->stage(), 'failure_reason' => $reason->value],
                $reason->label(),
                $request->user()?->getKey(),
            );

            throw $exception;
        }

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

    /** @return array<string, mixed> */
    private function proctorIdActivityState(string $operation, string $enteredProctorId, string $verificationStatus, array $extra = []): array
    {
        return [
            'proctor_id_activity' => true,
            'entered_proctor_id' => $enteredProctorId,
            'operation' => $operation,
            'verification_status' => $verificationStatus,
        ] + $extra;
    }
}
