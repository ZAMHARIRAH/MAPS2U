<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_code')->nullable()->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('request_type_id')->constrained('request_types');
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('full_name');
            $table->string('phone_number');
            $table->unsignedTinyInteger('urgency_level')->nullable();
            $table->json('answers');
            $table->json('attachments')->nullable();
            $table->foreignId('related_request_id')->nullable()->constrained('client_requests')->nullOnDelete();
            $table->string('status')->default('Submitted');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_requests');
    }
};
