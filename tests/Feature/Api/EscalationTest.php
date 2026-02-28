<?php
use App\Actions\Referral\EscalateReferralAction;
use App\Enums\ReferralStatus;
use App\Enums\StaffRole;
use App\Enums\UrgencyLevel;
use App\Models\Referral;
use App\Models\Staff;

it('escalates an unacknowledged emergency referral and notifies all admins', function () {
    $admin1   = Staff::factory()->create(['role' => StaffRole::Admin]);
    $admin2   = Staff::factory()->create(['role' => StaffRole::Admin]);
    $referral = Referral::factory()->create([
        'urgency_level' => UrgencyLevel::Emergency,
        'status'        => ReferralStatus::Assigned,
    ]);

    $action = new EscalateReferralAction();
    $action->execute($referral);

    expect($referral->fresh()->status)->toBe(ReferralStatus::Escalated);

    $this->assertDatabaseCount('staff_notifications', 2); // one per admin
    $this->assertDatabaseHas('staff_notifications', ['staff_id' => $admin1->id, 'referral_id' => $referral->id]);
    $this->assertDatabaseHas('staff_notifications', ['staff_id' => $admin2->id, 'referral_id' => $referral->id]);
});

it('does not escalate a referral that has already been acknowledged', function () {
    Staff::factory()->create(['role' => StaffRole::Admin]);

    $referral = Referral::factory()->create([
        'urgency_level' => UrgencyLevel::Emergency,
        'status'        => ReferralStatus::Acknowledged,
    ]);

    $action = new EscalateReferralAction();
    $action->execute($referral);

    // Status should remain acknowledged, not changed to escalated
    expect($referral->fresh()->status)->toBe(ReferralStatus::Acknowledged);
    $this->assertDatabaseCount('staff_notifications', 0);
});
