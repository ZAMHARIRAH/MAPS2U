<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('client_requests', 'schedule_reminder_sent_at')) {
                $table->timestamp('schedule_reminder_sent_at')->nullable()->after('scheduled_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('client_requests', function (Blueprint $table) {
            if (Schema::hasColumn('client_requests', 'schedule_reminder_sent_at')) {
                $table->dropColumn('schedule_reminder_sent_at');
            }
        });
    }
};
