<?php
namespace App\Enums;

enum ReferralStatus: string {
    case Pending      = 'pending';
    case Triaged      = 'triaged';
    case Assigned     = 'assigned';
    case Acknowledged = 'acknowledged';
    case InProgress   = 'in_progress';
    case Completed    = 'completed';
    case Cancelled    = 'cancelled';
    case Escalated    = 'escalated';

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending      => [self::Triaged, self::Cancelled],
            self::Triaged      => [self::Assigned, self::Cancelled],
            self::Assigned     => [self::Acknowledged, self::Cancelled, self::Escalated],
            self::Acknowledged => [self::InProgress, self::Cancelled],
            self::InProgress   => [self::Completed, self::Cancelled],
            self::Escalated    => [self::Assigned, self::Cancelled],
            self::Completed    => [],
            self::Cancelled    => [],
        };
    }

    public function canTransitionTo(ReferralStatus $new): bool
    {
        return in_array($new, $this->allowedTransitions(), strict: true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], strict: true);
    }
}
