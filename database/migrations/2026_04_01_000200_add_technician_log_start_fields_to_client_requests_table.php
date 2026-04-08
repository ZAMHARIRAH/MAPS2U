<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('client_requests', 'technician_log_started_at')) {
                $table->timestamp('technician_log_started_at')->nullable()->after('quotation_return_remark');
            }

            if (!Schema::hasColumn('client_requests', 'technician_log_started_label')) {
                $table->string('technician_log_started_label')->nullable()->after('technician_log_started_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('client_requests', function (Blueprint $table) {
            foreach (['technician_log_started_at', 'technician_log_started_label'] as $column) {
                if (Schema::hasColumn('client_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
