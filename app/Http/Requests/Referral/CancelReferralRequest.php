<?php
namespace App\Http\Requests\Referral;

use Illuminate\Foundation\Http\FormRequest;

final class CancelReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }
}
