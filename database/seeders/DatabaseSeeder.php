<?php
namespace Database\Seeders;

use App\Enums\HospitalStatus;
use App\Enums\StaffRole;
use App\Models\Hospital;
use App\Models\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Admin Staff ────────────────────────────────────────────────────
        $admin = Staff::create([
            'name'         => 'System Admin',
            'email'        => 'admin@healthcare.local',
            'password'     => Hash::make('password'),
            'role'         => StaffRole::Admin,
            'department'   => null,
            'is_available' => true,
        ]);

        // ─── Doctors ────────────────────────────────────────────────────────
        Staff::create([
            'name'         => 'Dr. Sarah Chen',
            'email'        => 'sarah.chen@healthcare.local',
            'password'     => Hash::make('password'),
            'role'         => StaffRole::Doctor,
            'department'   => 'cardiology',
            'is_available' => true,
        ]);

        Staff::create([
            'name'         => 'Dr. James Wilson',
            'email'        => 'james.wilson@healthcare.local',
            'password'     => Hash::make('password'),
            'role'         => StaffRole::Doctor,
            'department'   => 'neurology',
            'is_available' => true,
        ]);

        // ─── Coordinator ────────────────────────────────────────────────────
        Staff::create([
            'name'         => 'Coordinator Alex Patel',
            'email'        => 'alex.patel@healthcare.local',
            'password'     => Hash::make('password'),
            'role'         => StaffRole::Coordinator,
            'department'   => 'cardiology',
            'is_available' => true,
        ]);

        // ─── Hospitals ──────────────────────────────────────────────────────
        $plainKey1 = 'hsp_seed_hospital_one_key_12345678901';
        Hospital::create([
            'name'         => 'City General Hospital',
            'code'         => 'CGH001',
            'status'       => HospitalStatus::Active,
            'api_key_hash' => hash('sha256', $plainKey1),
        ]);

        $plainKey2 = 'hsp_seed_hospital_two_key_12345678901';
        Hospital::create([
            'name'         => 'North Medical Centre',
            'code'         => 'NMC002',
            'status'       => HospitalStatus::Active,
            'api_key_hash' => hash('sha256', $plainKey2),
        ]);

        $this->command->info('Seeded successfully.');
        $this->command->table(
            ['Resource', 'Credential'],
            [
                ['Admin login', 'admin@healthcare.local / password'],
                ['Hospital 1 API Key', $plainKey1],
                ['Hospital 2 API Key', $plainKey2],
            ]
        );
    }
}
