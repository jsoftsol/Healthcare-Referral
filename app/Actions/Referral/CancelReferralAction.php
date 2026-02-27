<?php
namespace App\Actions\Referral;

use App\Enums\ReferralStatus;
use App\Events\ReferralStatusChanged;
use App\Exceptions\InvalidReferralTransitionException;
use App\Models\Referral;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

final class CancelReferralAction
{
    public function execute(Referral $referral, string $reason, Staff $performedBy): Referral
    {
        if (! $referral->canBeCancelled()) {
            throw new InvalidReferralTransitionException(
                "Referral [{$referral->id}] cannot be cancelled — it is already {$referral->status->value}."
            );
        }

        return DB::transaction(function () use ($referral, $reason, $performedBy): Referral {
            $oldStatus = $referral->status;

            $referral->update([
                'status'              => ReferralStatus::Cancelled,
                'cancellation_reason' => $reason,
            ]);

            event(new ReferralStatusChanged($referral, $oldStatus, ReferralStatus::Cancelled, $performedBy));

            return $referral->fresh();
        });
    }
}
