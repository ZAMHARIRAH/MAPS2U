<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_requests', function (Blueprint $table) {
            $table->longText('viewer_summary_remark')->nullable()->after('admin_technician_remarks');
            $table->longText('viewer_summary_signature')->nullable()->after('viewer_summary_remark');
            $table->string('viewer_summary_updated_by_name')->nullable()->after('viewer_summary_signature');
            $table->timestamp('viewer_summary_updated_at')->nullable()->after('viewer_summary_updated_by_name');
            $table->json('viewer_summary_history')->nullable()->after('viewer_summary_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('client_requests', function (Blueprint $table) {
            $table->dropColumn([
                'viewer_summary_remark',
                'viewer_summary_signature',
                'viewer_summary_updated_by_name',
                'viewer_summary_updated_at',
                'viewer_summary_history',
            ]);
        });
    }
};
