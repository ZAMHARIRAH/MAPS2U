<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Facilities Management',
            'Infrastructure Planning',
            'ICT Operations',
            'Asset & Maintenance',
        ] as $name) {
            Department::updateOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
