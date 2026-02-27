<?php
namespace App\Actions\Referral;

use App\Enums\ReferralStatus;
use App\Events\ReferralTriaged;
use App\Models\Referral;
use App\Services\AiTriageService;
use Illuminate\Support\Facades\DB;

final class TriageReferralAction
{
    public function __construct(
        private readonly AiTriageService $aiTriageService
    ) {}

    public function execute(Referral $referral): Referral
    {
        $inputPayload = [
            'icd10_codes'    => $referral->icd10_codes,
            'clinical_notes' => $referral->clinical_notes,
            'urgency_level'  => $referral->urgency_level->value,
        ];

        $result = $this->aiTriageService->assess($inputPayload);

        return DB::transaction(function () use ($referral, $inputPayload, $result): Referral {
            $referral->update([
                'status'                  => ReferralStatus::Triaged,
                'ai_suggested_department' => $result['department'],
                'ai_confidence_score'     => $result['confidence_score'],
                'ai_processed_at'         => now(),
                'ai_input_payload'        => $inputPayload,
                'ai_output_payload'       => $result,
            ]);

            event(new ReferralTriaged($referral));

            return $referral->fresh();
        });
    }
}
