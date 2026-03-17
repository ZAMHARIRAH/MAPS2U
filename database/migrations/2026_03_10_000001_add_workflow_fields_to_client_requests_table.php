<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_requests', function (Blueprint $table) {
            $table->foreignId('assigned_technician_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->json('technician_review')->nullable()->after('attachments');
            $table->text('technician_return_remark')->nullable()->after('technician_review');
            $table->timestamp('technician_review_updated_at')->nullable()->after('technician_return_remark');
            $table->json('costing_entries')->nullable()->after('technician_review_updated_at');
            $table->json('costing_receipts')->nullable()->after('costing_entries');
            $table->json('quotation_entries')->nullable()->after('costing_receipts');
            $table->unsignedTinyInteger('approved_quotation_index')->nullable()->after('quotation_entries');
            $table->timestamp('quotation_submitted_at')->nullable()->after('approved_quotation_index');
        });
    }

    public function down(): void
    {
        Schema::table('client_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_technician_id');
            $table->dropColumn([
                'technician_review',
                'technician_return_remark',
                'technician_review_updated_at',
                'costing_entries',
                'costing_receipts',
                'quotation_entries',
                'approved_quotation_index',
                'quotation_submitted_at',
            ]);
        });
    }
};
