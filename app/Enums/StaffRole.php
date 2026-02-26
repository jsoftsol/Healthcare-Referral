<?php
namespace App\Enums;

enum StaffRole: string {
    case Admin       = 'admin';
    case Doctor      = 'doctor';
    case Coordinator = 'coordinator';

    public function canManageReferrals(): bool
    {
        return $this === self::Admin;
    }

    public function receivesReferralNotifications(): bool
    {
        return in_array($this, [self::Doctor, self::Coordinator], strict: true);
    }
}
