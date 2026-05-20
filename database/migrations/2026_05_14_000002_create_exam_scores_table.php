<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')
                  ->constrained('applicants')
                  ->onDelete('cascade');
            $table->foreignId('exam_schedule_id')
                  ->constrained('exam_schedules')
                  ->onDelete('cascade');
            $table->decimal('score', 5, 2)->nullable();       // e.g. 87.50
            $table->decimal('total_items', 5, 2)->default(100);
            $table->text('remarks')->nullable();
            $table->string('recorded_by')->nullable();        // SSO user id/name of admission officer
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->unique(['applicant_id', 'exam_schedule_id']); // one score per exam per applicant
            $table->index('applicant_id');
            $table->index('exam_schedule_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_scores');
    }
};
