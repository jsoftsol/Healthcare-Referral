<?php
namespace App\Http\Requests\Referral;

use Illuminate\Foundation\Http\FormRequest;

final class AssignReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
        ];
    }
}
