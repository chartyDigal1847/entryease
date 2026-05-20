<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('title');                          // e.g. "Grade 7 Entrance Exam – Batch A"
            $table->date('exam_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('venue')->nullable();
            $table->string('batch')->nullable();              // e.g. "Batch A", "Batch B"
            $table->integer('slots')->default(50);            // max examinees
            $table->text('instructions')->nullable();
            $table->enum('status', ['upcoming', 'ongoing', 'completed', 'cancelled'])->default('upcoming');
            $table->timestamps();

            $table->index('exam_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_schedules');
    }
};
