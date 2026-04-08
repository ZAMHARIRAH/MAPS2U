<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'admin')
            ->where('sub_role', 'super_admin')
            ->update(['sub_role' => 'viewer']);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('role', 'admin')
            ->where('sub_role', 'viewer')
            ->where('email', '!=', 'viewer@maps2u.com')
            ->update(['sub_role' => 'super_admin']);
    }
};
