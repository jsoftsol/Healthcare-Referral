<?php
namespace App\Http\Controllers\Api\V1;

use App\Enums\ReferralStatus;
use App\Models\Referral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ReportingController extends BaseController
{
    /**
     * GET /api/v1/admin/reports
     */
    public function index(Request $request): JsonResponse
    {
        $dateFrom = $request->date_from ? now()->parse($request->date_from) : now()->subDays(30);
        $dateTo   = $request->date_to ? now()->parse($request->date_to) : now();

        $baseQuery = Referral::whereBetween('created_at', [$dateFrom, $dateTo]);

        $totalReferrals = (clone $baseQuery)->count();

        // Referrals per day
        $perDay = (clone $baseQuery)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date');

        // Average AI confidence score
        $avgConfidence = (clone $baseQuery)
            ->whereNotNull('ai_confidence_score')
            ->avg('ai_confidence_score');

        // Escalation rate
        $escalated = (clone $baseQuery)
            ->where('status', ReferralStatus::Escalated->value)
            ->count();

        // Cancellation rate
        $cancelled = (clone $baseQuery)
            ->where('status', ReferralStatus::Cancelled->value)
            ->count();

        return $this->success([
            'period'                => [
                'from' => $dateFrom->toDateString(),
                'to'   => $dateTo->toDateString(),
            ],
            'total_referrals'       => $totalReferrals,
            'referrals_per_day'     => $perDay,
            'average_ai_confidence' => $avgConfidence ? round((float) $avgConfidence, 4) : null,
            'escalation_rate'       => $totalReferrals > 0
                ? round($escalated / $totalReferrals * 100, 2)
                : 0,
            'cancellation_rate'     => $totalReferrals > 0
                ? round($cancelled / $totalReferrals * 100, 2)
                : 0,
            'escalated_count'       => $escalated,
            'cancelled_count'       => $cancelled,
        ]);
    }
}
