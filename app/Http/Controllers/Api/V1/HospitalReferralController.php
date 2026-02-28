<?php
namespace App\Http\Controllers\Api\V1;

use App\Actions\Referral\SubmitReferralAction;
use App\Http\Requests\Referral\SubmitReferralRequest;
use App\Http\Resources\Referral\ReferralResource;
use Illuminate\Http\JsonResponse;

final class HospitalReferralController extends BaseController
{
    public function __construct(
        private readonly SubmitReferralAction $submitAction
    ) {}

    /**
     * POST /api/v1/hospital/referrals
     *
     * Submit a new referral. Authenticated via X-Hospital-Api-Key header.
     */
    public function store(SubmitReferralRequest $request): JsonResponse
    {
        $hospital = $request->attributes->get('hospital');
        $referral = $this->submitAction->execute($hospital, $request->validated());

        return $this->created(
            new ReferralResource($referral->load(['patient', 'hospital'])),
            'Referral submitted successfully.'
        );
    }
}
