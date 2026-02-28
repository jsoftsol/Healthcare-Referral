<?php
namespace App\Http\Resources\Referral;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ReferralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'status'              => $this->status->value,
            'urgency_level'       => $this->urgency_level->value,
            'department'          => $this->department,
            'icd10_codes'         => $this->icd10_codes,
            'hospital'            => [
                'id'   => $this->hospital->id,
                'name' => $this->hospital->name,
                'code' => $this->hospital->code,
            ],
            'assigned_staff'      => $this->whenLoaded('assignedStaff', fn() => [
                'id'         => $this->assignedStaff->id,
                'name'       => $this->assignedStaff->name,
                'department' => $this->assignedStaff->department,
            ]),
            'ai_triage'           => $this->when($this->ai_processed_at !== null, [
                'suggested_department' => $this->ai_suggested_department,
                'confidence_score'     => $this->ai_confidence_score,
                'processed_at'         => $this->ai_processed_at?->toIso8601String(),
            ]),
            'cancellation_reason' => $this->cancellation_reason,
            'created_at'          => $this->created_at->toIso8601String(),
            'updated_at'          => $this->updated_at->toIso8601String(),
            'patient'             => $this->whenLoaded('patient', fn() => [
                'id'               => $this->patient->id,
                'first_name'       => $this->patient->first_name,
                'last_name'        => $this->patient->last_name,
                'date_of_birth'    => $this->patient->date_of_birth,
                'insurance_number' => $this->patient->insurance_number,
            ]),
            'audit_history'       => AuditLogResource::collection(
                $this->whenLoaded('auditLogs')
            ),
        ];
    }
}
