<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('state')->nullable()->after('address');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('region_states')->nullable()->after('sub_role');
        });

        DB::statement("ALTER TABLE request_types MODIFY role_scope ENUM('hq_staff','kindergarten','ssu','all','both') DEFAULT 'all'");
        DB::table('request_types')->where('role_scope', 'both')->update(['role_scope' => 'all']);

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('ssm_number')->nullable();
            $table->text('office_address')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('fax_number')->nullable();
            $table->string('official_email')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('bank')->nullable();
            $table->string('account_number_for_payment')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_original_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('region_states');
        });
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('state');
        });
        DB::table('request_types')->where('role_scope', 'all')->update(['role_scope' => 'both']);
        DB::statement("ALTER TABLE request_types MODIFY role_scope ENUM('hq_staff','kindergarten','both') DEFAULT 'both'");
    }
};
