<?php
namespace App\Http\Requests\Referral;

use App\Enums\UrgencyLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SubmitReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Hospital auth handled by middleware
    }

    public function rules(): array
    {
        return [
            // Patient
            'patient.first_name'       => ['required', 'string', 'max:100'],
            'patient.last_name'        => ['required', 'string', 'max:100'],
            'patient.date_of_birth'    => ['required', 'date', 'before:today'],
            'patient.national_id'      => ['required', 'string', 'max:50'],
            'patient.insurance_number' => ['required', 'string', 'max:50'],

            // Referral
            'urgency_level'            => ['required', Rule::enum(UrgencyLevel::class)],
            'icd10_codes'              => ['required', 'array', 'min:1'],
            'icd10_codes.*'            => ['required', 'string', 'regex:/^[A-Z][0-9]{2}(\.[0-9A-Z]{1,4})?$/'],
            'clinical_notes'           => ['required', 'string', 'min:10'],
            'department'               => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'icd10_codes.*.regex' => 'Each ICD-10 code must be in valid format (e.g. I21, I21.0).',
        ];
    }
}
