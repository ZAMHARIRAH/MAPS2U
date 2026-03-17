<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin.maps@maps2u.com'], [
            'name' => 'Admin MAPS', 'phone_number' => '012-1111111', 'address' => 'MAPS2U HQ',
            'role' => User::ROLE_ADMIN, 'sub_role' => User::ADMIN_MAPS, 'password' => 'AdminMaps123!'
        ]);

        User::updateOrCreate(['email' => 'admin.aim@maps2u.com'], [
            'name' => 'Admin AIM', 'phone_number' => '012-2222222', 'address' => 'AIM HQ',
            'role' => User::ROLE_ADMIN, 'sub_role' => User::ADMIN_AIM, 'password' => 'AdminAim123!'
        ]);
    }
}
