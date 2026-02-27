<?php
namespace App\Listeners;

use App\Events\ReferralSubmitted;
use App\Jobs\ProcessAiTriageJob;

final class DispatchAiTriageListener
{
    public function handle(ReferralSubmitted $event): void
    {
        ProcessAiTriageJob::dispatch($event->referral)
            ->onQueue('triage');
    }
}
