<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE request_questions MODIFY question_type ENUM('remark','radio','date_range','checkbox','task_title')");
    }

    public function down(): void
    {
        DB::table('request_questions')
            ->where('question_type', 'task_title')
            ->update(['question_type' => 'radio']);

        DB::statement("ALTER TABLE request_questions MODIFY question_type ENUM('remark','radio','date_range','checkbox')");
    }
};
