<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('client_requests', 'legacy_import_email')) {
            DB::statement('ALTER TABLE client_requests ADD legacy_import_email VARCHAR(255) NULL AFTER user_id');
            DB::statement('CREATE INDEX client_requests_legacy_import_email_index ON client_requests (legacy_import_email)');
        }

        try {
            DB::statement('ALTER TABLE client_requests MODIFY user_id BIGINT UNSIGNED NULL');
        } catch (\Throwable $e) {
            // Some database engines or hosting panels may already allow NULL or may not support MODIFY.
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('client_requests', 'legacy_import_email')) {
            DB::statement('DROP INDEX client_requests_legacy_import_email_index ON client_requests');
            DB::statement('ALTER TABLE client_requests DROP COLUMN legacy_import_email');
        }
    }
};
