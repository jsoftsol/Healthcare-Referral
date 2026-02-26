<?php
namespace App\Enums;
enum UrgencyLevel: string {
    case Routine   = 'routine';
    case Urgent    = 'urgent';
    case Emergency = 'emergency';

    public function priority(): int
    {
        return match($this) {
            self::Routine   => 1,
            self::Urgent    => 2,
            self::Emergency => 3,
        };
    }

    public function escalationWindowSeconds(): ?int
    {
        return match($this) {
            self::Emergency => (int) config('referral.emergency_escalation_delay', 120),
            default         => null,
        };
    }
}
