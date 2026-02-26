<?php
namespace Database\Factories;

use App\Enums\StaffRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Staff;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Staff>
 */
class StaffFactory extends Factory
{
    protected $model = Staff::class;

    public function definition(): array
    {
        return [
            'name'         => $this->faker->name(),
            'email'        => $this->faker->unique()->safeEmail(),
            'password'     => Hash::make('password'),
            'role'         => $this->faker->randomElement(StaffRole::cases()),
            'department'   => $this->faker->randomElement(['cardiology', 'neurology', 'oncology', 'emergency']),
            'is_available' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => StaffRole::Admin, 'department' => null]);
    }

    public function doctor(string $department = 'cardiology'): static
    {
        return $this->state(['role' => StaffRole::Doctor, 'department' => $department]);
    }

    public function coordinator(string $department = 'cardiology'): static
    {
        return $this->state(['role' => StaffRole::Coordinator, 'department' => null]);
    }

    public function unavailable(): static
    {
        return $this->state(['is_available' => false]);
    }
}
