<?php
namespace App\Events;

use App\Enums\ReferralStatus;
use App\Models\Referral;
use App\Models\Staff;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReferralStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Referral $referral,
        public readonly ReferralStatus $oldStatus,
        public readonly ReferralStatus $newStatus,
        public readonly ?Staff $performedBy
    ) {}
}
