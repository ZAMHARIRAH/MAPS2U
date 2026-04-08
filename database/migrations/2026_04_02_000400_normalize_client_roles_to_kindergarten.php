<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('users')
            ->whereIn('sub_role', ['teacher', 'principal'])
            ->update(['sub_role' => 'kindergarten']);

        DB::table('request_types')
            ->where('role_scope', 'teacher_principal')
            ->update(['role_scope' => 'kindergarten']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE request_types MODIFY role_scope ENUM('hq_staff', 'kindergarten', 'both') DEFAULT 'both'");
        }
    }

    public function down(): void
    {
        DB::table('users')
            ->where('sub_role', 'kindergarten')
            ->update(['sub_role' => 'teacher']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE request_types MODIFY role_scope ENUM('hq_staff', 'teacher_principal', 'kindergarten', 'both') DEFAULT 'both'");
        }

        DB::table('request_types')
            ->where('role_scope', 'kindergarten')
            ->update(['role_scope' => 'teacher_principal']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE request_types MODIFY role_scope ENUM('hq_staff', 'teacher_principal', 'both') DEFAULT 'both'");
        }
    }
};
