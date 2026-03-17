<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('client_requests', 'admin_approval_status')) {
                $table->string('admin_approval_status')->default('pending')->after('status');
            }
            if (!Schema::hasColumn('client_requests', 'admin_approval_remark')) {
                $table->text('admin_approval_remark')->nullable()->after('admin_approval_status');
            }
            if (!Schema::hasColumn('client_requests', 'admin_approved_at')) {
                $table->timestamp('admin_approved_at')->nullable()->after('admin_approval_remark');
            }
            if (!Schema::hasColumn('client_requests', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('admin_approved_at');
            }
            if (!Schema::hasColumn('client_requests', 'quotation_return_remark')) {
                $table->text('quotation_return_remark')->nullable()->after('quotation_submitted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('client_requests', function (Blueprint $table) {
            foreach (['quotation_return_remark','assigned_at','admin_approved_at','admin_approval_remark','admin_approval_status'] as $col) {
                if (Schema::hasColumn('client_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
