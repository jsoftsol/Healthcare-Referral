<?php

namespace Database\Factories;

use App\Enums\HospitalStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Hospital;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Hospital>
 */
class HospitalFactory extends Factory
{
    protected $model = Hospital::class;

    public function definition(): array
    {
        $apiKey = 'hsp_' . $this->faker->regexify('[A-Za-z0-9]{40}');

        return [
            'name'        => $this->faker->company() . ' Hospital',
            'code'        => strtoupper($this->faker->unique()->lexify('HSP???')),
            'status'      => HospitalStatus::Active,
            'api_key_hash' => hash('sha256', $apiKey),
        ];
    }

    public function suspended(): static
    {
        return $this->state(['status' => HospitalStatus::Suspended]);
    }
}
