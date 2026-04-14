<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('request_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('role_scope', ['hq_staff', 'kindergarten', 'both'])->default('both');
            $table->boolean('urgency_enabled')->default(false);
            $table->boolean('attachment_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('request_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_type_id')->constrained()->cascadeOnDelete();
            $table->text('question_text');
            $table->enum('question_type', ['remark', 'radio', 'date_range', 'checkbox', 'task_title']);
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('is_required')->default(true);
            $table->string('start_label')->nullable();
            $table->string('end_label')->nullable();
            $table->timestamps();
        });

        Schema::create('request_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_question_id')->constrained()->cascadeOnDelete();
            $table->string('option_text');
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('allows_other_text')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_question_options');
        Schema::dropIfExists('request_questions');
        Schema::dropIfExists('request_types');
    }
};
