<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_requests', function (Blueprint $table) {
            $table->json('inspection_sessions')->nullable()->after('inspect_data');
            $table->timestamp('invoice_uploaded_at')->nullable()->after('invoice_files');
            $table->json('customer_service_report')->nullable()->after('invoice_uploaded_at');
            $table->timestamp('technician_completed_at')->nullable()->after('customer_service_report');
            $table->json('finance_form')->nullable()->after('technician_completed_at');
            $table->timestamp('finance_completed_at')->nullable()->after('finance_form');
        });
    }

    public function down(): void
    {
        Schema::table('client_requests', function (Blueprint $table) {
            $table->dropColumn([
                'inspection_sessions',
                'invoice_uploaded_at',
                'customer_service_report',
                'technician_completed_at',
                'finance_form',
                'finance_completed_at',
            ]);
        });
    }
};
