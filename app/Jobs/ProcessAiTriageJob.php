<?php
namespace App\Jobs;

use App\Actions\Referral\TriageReferralAction;
use App\Exceptions\AiTriageException;
use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAiTriageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [5, 25, 125];

    public function __construct(
        private readonly Referral $referral
    ) {}

    public function handle(TriageReferralAction $action): void
    {
        $action->execute($this->referral);
    }

    public function failed(AiTriageException $exception): void
    {
        report($exception);
    }
}
