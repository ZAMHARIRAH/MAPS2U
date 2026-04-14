<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('report_archives', function (Blueprint $table) {
            $table->id();
            $table->string('report_type', 40);
            $table->unsignedInteger('archive_year');
            $table->json('payload');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['report_type', 'archive_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_archives');
    }
};
