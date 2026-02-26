<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null; // Audit logs are immutable; no updated_at

    protected $fillable = [
        'referral_id',
        'performed_by',
        'action',
        'field_name',
        'old_value',
        'new_value',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'performed_by');
    }
}
