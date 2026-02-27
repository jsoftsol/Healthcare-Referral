<?php
namespace App\Events;

use App\Models\Referral;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReferralSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Referral $referral
    ) {}
}
