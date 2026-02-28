<?php
use App\Enums\HospitalStatus;
use App\Jobs\ProcessAiTriageJob;
use App\Models\Hospital;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('allows an active hospital to submit a referral', function () {
    $hospital = Hospital::factory()->create(['status' => HospitalStatus::Active]);
    $apiKey   = 'hsp_testkey123';
    $hospital->update(['api_key_hash' => hash('sha256', $apiKey)]);

    $response = $this->withHeaders(['X-Hospital-Api-Key' => $apiKey])
        ->postJson('/api/v1/hospital/referrals', referralPayload());

    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => ['id', 'status', 'urgency_level', 'icd10_codes'],
        ])
        ->assertJsonPath('data.status', 'pending');

    Queue::assertPushed(ProcessAiTriageJob::class);
});

it('rejects requests with invalid API key', function () {
    $this->withHeaders(['X-Hospital-Api-Key' => 'invalid-key'])
        ->postJson('/api/v1/hospital/referrals', referralPayload())
        ->assertStatus(401)
        ->assertJsonPath('success', false);
});

it('rejects referral from a suspended hospital', function () {
    $hospital = Hospital::factory()->create(['status' => HospitalStatus::Suspended]);
    $apiKey   = 'hsp_testkey999';
    $hospital->update(['api_key_hash' => hash('sha256', $apiKey)]);

    $this->withHeaders(['X-Hospital-Api-Key' => $apiKey])
        ->postJson('/api/v1/hospital/referrals', referralPayload())
        ->assertStatus(403)
        ->assertJsonPath('success', false);

    Queue::assertNothingPushed();
});

it('returns validation errors for invalid ICD-10 codes', function () {
    $hospital = Hospital::factory()->create(['status' => HospitalStatus::Active]);
    $apiKey   = 'hsp_validkey';
    $hospital->update(['api_key_hash' => hash('sha256', $apiKey)]);

    $payload                = referralPayload();
    $payload['icd10_codes'] = ['NOT-VALID', '123'];

    $this->withHeaders(['X-Hospital-Api-Key' => $apiKey])
        ->postJson('/api/v1/hospital/referrals', $payload)
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['errors' => ['icd10_codes.0']]);
});

it('returns existing referral instead of creating duplicate', function () {
    $hospital = Hospital::factory()->create(['status' => HospitalStatus::Active]);
    $apiKey   = 'hsp_dupetest';
    $hospital->update(['api_key_hash' => hash('sha256', $apiKey)]);

    $payload = referralPayload();
    $headers = ['X-Hospital-Api-Key' => $apiKey];

    $first  = $this->withHeaders($headers)->postJson('/api/v1/hospital/referrals', $payload);
    $second = $this->withHeaders($headers)->postJson('/api/v1/hospital/referrals', $payload);

    $first->assertStatus(201);
    $second->assertStatus(201);
    expect($first->json('data.id'))->toBe($second->json('data.id'));

    $this->assertDatabaseCount('referrals', 1);
});

// ─── Helpers ────────────────────────────────────────────────────────────────

function referralPayload(array $overrides = []): array
{
    return array_merge([
        'patient'        => [
            'first_name'       => 'John',
            'last_name'        => 'Smith',
            'date_of_birth'    => '1970-06-20',
            'national_id'      => 'NID-ABC-' . uniqid(),
            'insurance_number' => 'INS-98765',
        ],
        'urgency_level'  => 'urgent',
        'icd10_codes'    => ['I21', 'I21.0'],
        'clinical_notes' => 'Chest pain, shortness of breath. ECG showing ST elevation.',
        'department'     => 'cardiology',
    ], $overrides);
}
