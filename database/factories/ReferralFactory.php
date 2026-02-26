<?php
namespace Database\Factories;

use App\Enums\ReferralStatus;
use App\Enums\UrgencyLevel;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Referral;
use App\Models\Patient;
use App\Models\Hospital;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Referral>
 */
class ReferralFactory extends Factory
{
    protected $model = Referral::class;

    public function definition(): array
    {
        return [
            'patient_id'     => Patient::factory(),
            'hospital_id'    => Hospital::factory(),
            'urgency_level'  => $this->faker->randomElement(UrgencyLevel::cases()),
            'status'         => ReferralStatus::Pending,
            'icd10_codes'    => [$this->faker->regexify('[A-Z][0-9]{2}')],
            'clinical_notes' => $this->faker->paragraph(),
            'department'     => $this->faker->randomElement(['cardiology', 'neurology', 'oncology']),
            'submitted_hash' => $this->faker->unique()->sha256(),
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => ReferralStatus::Pending]);
    }

    public function triaged(): static
    {
        return $this->state([
            'status'                  => ReferralStatus::Triaged,
            'ai_suggested_department' => 'cardiology',
            'ai_confidence_score'     => 0.87,
            'ai_processed_at'         => now(),
        ]);
    }

    public function assigned(): static
    {
        return $this->state(['status' => ReferralStatus::Assigned]);
    }

    public function completed(): static
    {
        return $this->state(['status' => ReferralStatus::Completed]);
    }

    public function emergency(): static
    {
        return $this->state(['urgency_level' => UrgencyLevel::Emergency]);
    }
}
