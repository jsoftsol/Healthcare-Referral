<?php
namespace App\Actions\Referral;

use App\Enums\ReferralStatus;
use App\Events\ReferralSubmitted;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\Referral;
use Illuminate\Support\Facades\DB;

final class SubmitReferralAction
{
    public function execute(Hospital $hospital, array $data): Referral
    {
        return DB::transaction(function () use ($hospital, $data): Referral {
            $patient = $this->resolvePatient($data['patient']);
            $hash    = $this->buildSubmissionHash($hospital->id, $patient->id, $data);

            // Idempotency: return existing if already submitted
            $existing = Referral::where('submitted_hash', $hash)->first();
            if ($existing !== null) {
                return $existing;
            }

            $referral = Referral::create([
                'patient_id'     => $patient->id,
                'hospital_id'    => $hospital->id,
                'urgency_level'  => $data['urgency_level'],
                'icd10_codes'    => $data['icd10_codes'],
                'clinical_notes' => $data['clinical_notes'],
                'department'     => $data['department'] ?? null,
                'submitted_hash' => $hash,
                'status'         => $data['status'] ?? ReferralStatus::Pending,
            ]);

            event(new ReferralSubmitted($referral));

            return $referral;
        });
    }

    private function resolvePatient(array $patientData): Patient
    {
        /** @var Patient|null $existing */
        $existing = Patient::findByNationalId($patientData['national_id']);

        if ($existing !== null) {
            return $existing;
        }

        $hash = hash_hmac(
            'sha256',
            $patientData['national_id'],
            config('referral.patient_id_hmac_key')
        );

        return Patient::create([
            'first_name'       => $patientData['first_name'],
            'last_name'        => $patientData['last_name'],
            'date_of_birth'    => $patientData['date_of_birth'],
            'national_id'      => $patientData['national_id'],
            'national_id_hash' => $hash,
            'insurance_number' => $patientData['insurance_number'],
        ]);
    }

    private function buildSubmissionHash(int $hospitalId, int $patientId, array $data): string
    {
        $codes = $data['icd10_codes'];
        sort($codes);

        return hash('sha256', implode('|', [
            $hospitalId,
            $patientId,
            implode(',', $codes),
            $data['urgency_level'],
        ]));
    }
}
