<?php
namespace App\Http\Controllers\Api\V1;

use App\Actions\Referral\AssignReferralAction;
use App\Actions\Referral\CancelReferralAction;
use App\Http\Requests\Referral\AssignReferralRequest;
use App\Http\Requests\Referral\CancelReferralRequest;
use App\Http\Requests\Referral\ListReferralsRequest;
use App\Http\Resources\Referral\ReferralResource;
use App\Models\Referral;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;

final class AdminReferralController extends BaseController
{
    public function __construct(
        private readonly AssignReferralAction $assignAction,
        private readonly CancelReferralAction $cancelAction
    ) {}

    /**
     * GET /api/v1/admin/referrals
     * List all referrals with filters and pagination.
     */
    public function index(ListReferralsRequest $request): JsonResponse
    {
        $query = Referral::with(['patient', 'hospital', 'assignedStaff'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->urgency, fn($q) => $q->where('urgency_level', $request->urgency))
            ->when($request->department, fn($q) => $q->where('department', $request->department))
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->latest();

        $referrals = $query->paginate($request->per_page ?? 20);

        return $this->success(
            ReferralResource::collection($referrals)->response()->getData(true)
        );
    }

    /**
     * GET /api/v1/admin/referrals/{referral}
     * View a single referral with full audit history.
     */
    public function show(Referral $referral): JsonResponse
    {
        $referral->load([
            'patient',
            'hospital',
            'assignedStaff',
            'auditLogs.performer',
        ]);

        return $this->success(new ReferralResource($referral));
    }

    /**
     * PATCH /api/v1/admin/referrals/{referral}/assign
     */
    public function assign(AssignReferralRequest $request, Referral $referral): JsonResponse
    {
        $staff   = Staff::findOrFail($request->staff_id);
        $updated = $this->assignAction->execute($referral, $staff, $request->user());

        return $this->success(
            new ReferralResource($updated->load(['assignedStaff'])),
            'Referral assigned successfully.'
        );
    }

    /**
     * PATCH /api/v1/admin/referrals/{referral}/cancel
     */
    public function cancel(CancelReferralRequest $request, Referral $referral): JsonResponse
    {
        $updated = $this->cancelAction->execute($referral, $request->reason, $request->user());

        return $this->success(
            new ReferralResource($updated),
            'Referral cancelled.'
        );
    }
}
