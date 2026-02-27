<?php
namespace App\Listeners;

use App\Events\ReferralAssigned;
use App\Events\ReferralStatusChanged;
use App\Events\ReferralSubmitted;
use App\Events\ReferralTriaged;
use App\Models\AuditLog;
use Illuminate\Events\Dispatcher;

/**
 * Centralized audit logging listener.
 * Subscribes to all referral events and writes immutable audit records.
 */
final class LogReferralAuditListener
{
    public function handleSubmitted(ReferralSubmitted $event): void
    {
        AuditLog::create([
            'referral_id'  => $event->referral->id,
            'performed_by' => null, // external hospital system
            'action'       => 'referral_submitted',
            'field_name'   => 'status',
            'old_value'    => null,
            'new_value'    => $event->referral->status->value,
            'metadata'     => [
                'hospital_id'   => $event->referral->hospital_id,
                'urgency_level' => $event->referral->urgency_level->value,
                'icd10_codes'   => $event->referral->icd10_codes,
            ],
        ]);
    }

    public function handleTriaged(ReferralTriaged $event): void
    {
        AuditLog::create([
            'referral_id'  => $event->referral->id,
            'performed_by' => null, // AI system
            'action'       => 'ai_triage_completed',
            'field_name'   => 'status',
            'old_value'    => 'pending',
            'new_value'    => $event->referral->status->value,
            'metadata'     => [
                'ai_suggested_department' => $event->referral->ai_suggested_department,
                'ai_confidence_score'     => $event->referral->ai_confidence_score,
                'ai_processed_at'         => $event->referral->ai_processed_at?->toIso8601String(),
                // Note: ai_input/output_payload stored on referral record directly
            ],
        ]);
    }

    public function handleAssigned(ReferralAssigned $event): void
    {
        AuditLog::create([
            'referral_id'  => $event->referral->id,
            'performed_by' => $event->performedBy->id,
            'action'       => 'referral_assigned',
            'field_name'   => 'assigned_staff_id',
            'old_value'    => null,
            'new_value'    => (string) $event->assignedTo->id,
            'metadata'     => [
                'assigned_to_name'       => $event->assignedTo->name,
                'assigned_to_department' => $event->assignedTo->department,
            ],
        ]);
    }

    public function handleStatusChanged(ReferralStatusChanged $event): void
    {
        AuditLog::create([
            'referral_id'  => $event->referral->id,
            'performed_by' => $event->performedBy?->id,
            'action'       => 'status_changed',
            'field_name'   => 'status',
            'old_value'    => $event->oldStatus->value,
            'new_value'    => $event->newStatus->value,
            'metadata'     => [
                'cancellation_reason' => $event->referral->cancellation_reason,
            ],
        ]);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            ReferralSubmitted::class     => 'handleSubmitted',
            ReferralTriaged::class       => 'handleTriaged',
            ReferralAssigned::class      => 'handleAssigned',
            ReferralStatusChanged::class => 'handleStatusChanged',
        ];
    }
}
