<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Referral\ReferralResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StaffReferralController extends BaseController
{
    /**
     * GET /api/v1/staff/referrals
     * List referrals assigned to the authenticated staff member.
     */
    public function index(Request $request): JsonResponse
    {
        $referrals = $request->user()
            ->referrals()
            ->with(['patient', 'hospital'])
            ->latest()
            ->paginate(20);

        return $this->success(
            ReferralResource::collection($referrals)->response()->getData(true)
        );
    }
}
