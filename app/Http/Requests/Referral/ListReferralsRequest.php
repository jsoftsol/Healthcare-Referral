<?php
namespace App\Http\Requests\Referral;

use App\Enums\ReferralStatus;
use App\Enums\UrgencyLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListReferralsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'     => ['nullable', Rule::enum(ReferralStatus::class)],
            'urgency'    => ['nullable', Rule::enum(UrgencyLevel::class)],
            'department' => ['nullable', 'string', 'max:100'],
            'date_from'  => ['nullable', 'date'],
            'date_to'    => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page'   => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
