<?php
use App\Actions\Referral\SubmitReferralAction;
use App\Enums\ReferralStatus;
use App\Events\ReferralSubmitted;
use App\Models\Hospital;
use App\Models\Referral;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake();
});

it('creates a new referral and patient when national_id does not exist', function () {
    $hospital = Hospital::factory()->create();
    $action   = new SubmitReferralAction();

    $referral = $action->execute($hospital, validReferralData());

    expect($referral)->toBeInstanceOf(Referral::class)
        ->and($referral->status)->toBe(ReferralStatus::Pending)
        ->and($referral->hospital_id)->toBe($hospital->id);

    $this->assertDatabaseCount('patients', 1);
    $this->assertDatabaseCount('referrals', 1);
});

it('reuses an existing patient when national_id matches', function () {
    $hospital = Hospital::factory()->create();
    $action   = new SubmitReferralAction();

    $data = validReferralData();

    // First submission creates patient
    $action->execute($hospital, $data);
    // Second submission with same national_id should reuse patient
    $data2                           = validReferralData();
    $data2['patient']['national_id'] = $data['patient']['national_id']; // same ID
    $data2['icd10_codes']            = ['I22'];                         // different code to avoid deduplication

    $action->execute($hospital, $data2);

    $this->assertDatabaseCount('patients', 1); // Only 1 patient created
    $this->assertDatabaseCount('referrals', 2);
});

it('returns an existing referral instead of creating a duplicate', function () {
    $hospital = Hospital::factory()->create();
    $action   = new SubmitReferralAction();

    $data = validReferralData();

    $first  = $action->execute($hospital, $data);
    $second = $action->execute($hospital, $data); // identical submission

    expect($first->id)->toBe($second->id);
    $this->assertDatabaseCount('referrals', 1);
});

it('dispatches a ReferralSubmitted event on successful submission', function () {
    $hospital = Hospital::factory()->create();
    $action   = new SubmitReferralAction();

    $action->execute($hospital, validReferralData());

    Event::assertDispatched(ReferralSubmitted::class);
});

it('does not dispatch ReferralSubmitted for duplicate submissions', function () {
    $hospital = Hospital::factory()->create();
    $action   = new SubmitReferralAction();
    $data     = validReferralData();

    $action->execute($hospital, $data);
    Event::fake();                      // reset after first call
    $action->execute($hospital, $data); // duplicate

    Event::assertNotDispatched(ReferralSubmitted::class);
});

// ─── Helpers ────────────────────────────────────────────────────────────────

function validReferralData(array $overrides = []): array
{
    return array_merge_recursive([
        'patient'        => [
            'first_name'       => 'Jane',
            'last_name'        => 'Doe',
            'date_of_birth'    => '1985-03-15',
            'national_id'      => 'NID-' . uniqid(),
            'insurance_number' => 'INS-12345',
            'status'           => ReferralStatus::Pending,
        ],
        'urgency_level'  => 'urgent',
        'icd10_codes'    => ['I21', 'I21.0'],
        'clinical_notes' => 'Patient presents with chest pain radiating to left arm.',
        'department'     => 'cardiology',
    ], $overrides);
}
