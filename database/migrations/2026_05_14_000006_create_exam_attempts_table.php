<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')
                  ->constrained('applicants')
                  ->onDelete('cascade');
            $table->foreignId('exam_schedule_id')
                  ->constrained('exam_schedules')
                  ->onDelete('cascade');
            $table->enum('status', ['in_progress', 'submitted'])->default('in_progress');
            $table->decimal('score', 6, 2)->default(0);
            $table->decimal('total_items', 6, 2)->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['applicant_id', 'exam_schedule_id']);
            $table->index(['applicant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempts');
    }
};
