<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_schedule_id')
                  ->constrained('exam_schedules')
                  ->onDelete('cascade');
            $table->text('question_text');
            $table->json('choices');
            $table->string('correct_answer', 1);
            $table->unsignedInteger('points')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['exam_schedule_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_questions');
    }
};
