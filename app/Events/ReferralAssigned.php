<?php
namespace App\Events;

use App\Models\Referral;
use App\Models\Staff;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReferralAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Referral $referral,
        public readonly Staff $assignedTo,
        public readonly Staff $performedBy
    ) {}
}
