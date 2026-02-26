<?php
namespace App\Models;

use App\Enums\ReferralStatus;
use App\Enums\UrgencyLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referral extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'hospital_id',
        'assigned_staff_id',
        'urgency_level',
        'status',
        'icd10_codes',
        'clinical_notes',
        'department',
        'ai_suggested_department',
        'ai_confidence_score',
        'ai_processed_at',
        'ai_input_payload',
        'ai_output_payload',
        'cancellation_reason',
        'submitted_hash',
    ];

    protected $casts = [
        'urgency_level'       => UrgencyLevel::class,
        'status'              => ReferralStatus::class,
        'icd10_codes'         => 'array',
        'ai_confidence_score' => 'float',
        'ai_processed_at'     => 'datetime',
        'ai_input_payload'    => 'array',
        'ai_output_payload'   => 'array',
    ];

    // Never log clinical notes or AI payloads
    protected $hidden = [
        'clinical_notes',
        'ai_input_payload',
        'ai_output_payload',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_staff_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class)->latest();
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(StaffNotification::class);
    }

    public function isEmergency(): bool
    {
        return $this->urgency_level === UrgencyLevel::Emergency;
    }

    public function canBeCancelled(): bool
    {
        return ! $this->status->isFinal();
    }

    public function canTransitionTo(ReferralStatus $status): bool
    {
        return $this->status->canTransitionTo($status);
    }
}
