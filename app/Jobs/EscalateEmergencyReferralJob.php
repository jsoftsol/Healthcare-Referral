<?php
namespace App\Jobs;

use App\Actions\Referral\EscalateReferralAction;
use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EscalateEmergencyReferralJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        private readonly Referral $referral
    ) {}

    public function handle(EscalateReferralAction $action): void
    {
        $this->referral->refresh();

        $action->execute($this->referral);
    }
}
