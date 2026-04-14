<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_requests', function (Blueprint $table) {
            $table->text('admin_approved_remark')->nullable()->after('admin_approval_remark');
            $table->text('subject_to_approval_remark')->nullable()->after('admin_approved_remark');
            $table->timestamp('subject_to_approval_requested_at')->nullable()->after('subject_to_approval_remark');
            $table->timestamp('subject_to_approval_checked_at')->nullable()->after('subject_to_approval_requested_at');
            $table->json('admin_technician_remarks')->nullable()->after('subject_to_approval_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('client_requests', function (Blueprint $table) {
            $table->dropColumn([
                'admin_approved_remark',
                'subject_to_approval_remark',
                'subject_to_approval_requested_at',
                'subject_to_approval_checked_at',
                'admin_technician_remarks',
            ]);
        });
    }
};
