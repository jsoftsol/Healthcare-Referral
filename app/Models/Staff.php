<?php
namespace App\Models;

use App\Enums\StaffRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Staff extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'staff';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'department',
        'is_available',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'role'              => StaffRole::class,
        'is_available'      => 'boolean',
        'email_verified_at' => 'datetime',
    ];

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'assigned_staff_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(StaffNotification::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'performed_by');
    }

    public function isAdmin(): bool
    {
        return $this->role === StaffRole::Admin;
    }

    public function isDoctor(): bool
    {
        return $this->role === StaffRole::Doctor;
    }

    public function isAvailable(): bool
    {
        return $this->is_available;
    }
}
