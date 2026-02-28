<?php
use App\Enums\ReferralStatus;
use App\Enums\StaffRole;
use App\Models\Referral;
use App\Models\Staff;

function makeAdmin(): Staff
{
    return Staff::factory()->create(['role' => StaffRole::Admin]);
}

function makeDoctor(string $department = 'cardiology'): Staff
{
    return Staff::factory()->create([
        'role'       => StaffRole::Doctor,
        'department' => $department,
    ]);
}

// ─── List Referrals ──────────────────────────────────────────────────────────

it('allows admin to list all referrals', function () {
    $admin = makeAdmin();
    Referral::factory()->count(5)->create();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/admin/referrals')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['data', 'meta']]);
});

it('denies non-admin from accessing admin referral list', function () {
    $doctor = makeDoctor();

    $this->actingAs($doctor, 'sanctum')
        ->getJson('/api/v1/admin/referrals')
        ->assertStatus(403);
});

it('filters referrals by status', function () {
    $admin = makeAdmin();
    Referral::factory()->count(3)->create(['status' => ReferralStatus::Pending]);
    Referral::factory()->count(2)->create(['status' => ReferralStatus::Triaged]);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/admin/referrals?status=pending')
        ->assertOk()
        ->assertJsonCount(3, 'data.data');
});

// ─── Assign Referral ─────────────────────────────────────────────────────────

it('allows admin to assign a triaged referral to a staff member', function () {
    $admin    = makeAdmin();
    $doctor   = makeDoctor();
    $referral = Referral::factory()->triaged()->create();

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/admin/referrals/{$referral->id}/assign", [
            'staff_id' => $doctor->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'assigned');

    expect($referral->fresh()->assigned_staff_id)->toBe($doctor->id);
});

it('rejects assignment when referral is in a non-assignable status', function () {
    $admin    = makeAdmin();
    $doctor   = makeDoctor();
    $referral = Referral::factory()->completed()->create();

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/admin/referrals/{$referral->id}/assign", [
            'staff_id' => $doctor->id,
        ])
        ->assertStatus(422);
});

// ─── Cancel Referral ─────────────────────────────────────────────────────────

it('allows admin to cancel a referral with a reason', function () {
    $admin    = makeAdmin();
    $referral = Referral::factory()->pending()->create();

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/admin/referrals/{$referral->id}/cancel", [
            'reason' => 'Patient withdrew consent for treatment.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

it('prevents cancelling an already completed referral', function () {
    $admin    = makeAdmin();
    $referral = Referral::factory()->completed()->create();

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/admin/referrals/{$referral->id}/cancel", [
            'reason' => 'Trying to cancel a completed one.',
        ])
        ->assertStatus(422);
});

// ─── View Single Referral ────────────────────────────────────────────────────

it('returns full referral detail including audit logs for admin', function () {
    $admin    = makeAdmin();
    $referral = Referral::factory()->pending()->create();

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/v1/admin/referrals/{$referral->id}")
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'status', 'audit_history'],
        ]);
});
