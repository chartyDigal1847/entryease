<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')
                  ->constrained('exam_attempts')
                  ->onDelete('cascade');
            $table->foreignId('exam_question_id')
                  ->constrained('exam_questions')
                  ->onDelete('cascade');
            $table->string('answer', 1)->nullable();
            $table->boolean('is_correct')->default(false);
            $table->decimal('points_awarded', 6, 2)->default(0);
            $table->timestamps();

            $table->unique(['exam_attempt_id', 'exam_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempt_answers');
    }
};
