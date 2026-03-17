<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_requests', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('location_id')->constrained('departments')->nullOnDelete();
            $table->date('scheduled_date')->nullable()->after('quotation_submitted_at');
            $table->string('scheduled_time')->nullable()->after('scheduled_date');
            $table->json('payment_receipt_files')->nullable()->after('scheduled_time');
            $table->string('payment_type')->nullable()->after('payment_receipt_files');
            $table->json('inspect_data')->nullable()->after('payment_type');
            $table->json('invoice_files')->nullable()->after('inspect_data');
            $table->json('feedback')->nullable()->after('invoice_files');
            $table->timestamp('customer_review_submitted_at')->nullable()->after('feedback');
        });
    }

    public function down(): void
    {
        Schema::table('client_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn([
                'scheduled_date',
                'scheduled_time',
                'payment_receipt_files',
                'payment_type',
                'inspect_data',
                'invoice_files',
                'feedback',
                'customer_review_submitted_at',
            ]);
        });
    }
};
