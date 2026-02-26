<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Patient;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        $nationalId = 'NID-' . $this->faker->unique()->numerify('########');
        $hmacKey    = config('referral.patient_id_hmac_key', config('app.key'));

        return [
            'first_name'       => $this->faker->firstName(),
            'last_name'        => $this->faker->lastName(),
            'date_of_birth'    => $this->faker->date('Y-m-d', '-18 years'),
            'national_id'      => $nationalId,
            'national_id_hash' => hash_hmac('sha256', $nationalId, $hmacKey),
            'insurance_number' => 'INS-' . $this->faker->numerify('########'),
        ];
    }
}
