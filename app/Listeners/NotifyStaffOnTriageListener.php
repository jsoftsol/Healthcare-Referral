<?php
namespace App\Listeners;

use App\Enums\StaffRole;
use App\Events\ReferralTriaged;
use App\Jobs\EscalateEmergencyReferralJob;
use App\Jobs\SendStaffNotificationJob;
use App\Models\Staff;
use App\Models\StaffNotification;

final class NotifyStaffOnTriageListener
{
    public function handle(ReferralTriaged $event): void
    {
        $referral   = $event->referral;
        $department = $referral->ai_suggested_department ?? $referral->department;

        // Find available staff in the matching department
        $eligibleStaff = Staff::where('role', '!=', StaffRole::Admin->value)
            ->where('department', $department)
            ->get();

        foreach ($eligibleStaff as $staff) {
            $notification = StaffNotification::create([
                'staff_id'    => $staff->id,
                'referral_id' => $referral->id,
                'message'     => $this->buildMessage($referral, $staff),
                'channel'     => 'in_app',
            ]);

            SendStaffNotificationJob::dispatch($notification)
                ->onQueue('notifications');
        }

        // Schedule emergency escalation if needed
        if ($referral->isEmergency()) {
            $delay = $referral->urgency_level->escalationWindowSeconds();

            EscalateEmergencyReferralJob::dispatch($referral)
                ->delay(now()->addSeconds($delay))
                ->onQueue('escalations');
        }
    }

    private function buildMessage($referral, Staff $staff): string
    {
        $urgency = strtoupper($referral->urgency_level->value);

        return "[{$urgency}] New referral #{$referral->id} requires attention. "
        . "Department: {$referral->ai_suggested_department}. "
        . "Codes: " . implode(', ', $referral->icd10_codes) . ".";
    }
}
