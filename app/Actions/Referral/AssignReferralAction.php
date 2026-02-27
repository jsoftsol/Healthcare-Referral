<?php
namespace App\Actions\Referral;

use App\Enums\ReferralStatus;
use App\Events\ReferralAssigned;
use App\Exceptions\InvalidReferralTransitionException;
use App\Models\Referral;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

final class AssignReferralAction
{
    public function execute(Referral $referral, Staff $staff, Staff $performedBy): Referral
    {
        if (! $referral->canTransitionTo(ReferralStatus::Assigned)) {
            throw new InvalidReferralTransitionException(
                "Referral [{$referral->id}] cannot be assigned from status [{$referral->status->value}]."
            );
        }

        return DB::transaction(function () use ($referral, $staff, $performedBy): Referral {
            $referral->update([
                'assigned_staff_id' => $staff->id,
                'status'            => ReferralStatus::Assigned,
            ]);

            event(new ReferralAssigned($referral, $staff, $performedBy));

            return $referral->fresh();
        });
    }
}
