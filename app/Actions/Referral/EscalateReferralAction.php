<?php
namespace App\Actions\Referral;

use App\Enums\ReferralStatus;
use App\Enums\StaffRole;
use App\Events\ReferralStatusChanged;
use App\Models\Referral;
use App\Models\Staff;
use App\Models\StaffNotification;
use Illuminate\Support\Facades\DB;

final class EscalateReferralAction
{
    public function execute(Referral $referral): void
    {
        if (! in_array($referral->status, [
            ReferralStatus::Assigned,
            ReferralStatus::Triaged,
        ], strict: true)) {
            return;
        }

        DB::transaction(function () use ($referral): void {
            $oldStatus = $referral->status;
            $referral->update(['status' => ReferralStatus::Escalated]);

            // Notify all admins
            $admins = Staff::where('role', StaffRole::Admin->value)->get();

            foreach ($admins as $admin) {
                StaffNotification::create([
                    'staff_id'    => $admin->id,
                    'referral_id' => $referral->id,
                    'message'     => "ESCALATION: Emergency referral #{$referral->id} has not been acknowledged within the required window.",
                    'channel' => 'in_app',
                    'sent_at' => now(),
                ]);
            }

            event(new ReferralStatusChanged(
                $referral,
                $oldStatus,
                ReferralStatus::Escalated,
                null// system-initiated
            ));
        });
    }
}
