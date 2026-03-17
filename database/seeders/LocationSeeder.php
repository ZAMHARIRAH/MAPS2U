<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        Location::insert([
            ['name' => 'MAPS2U HQ Putrajaya', 'type' => 'hq', 'address' => 'Putrajaya', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'MAPS2U Branch Selangor', 'type' => 'branch', 'address' => 'Selangor', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
