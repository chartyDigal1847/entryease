<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            if (!Schema::hasColumn('applicants', 'exam_schedule_id')) {
                $table->foreignId('exam_schedule_id')
                      ->nullable()
                      ->constrained('exam_schedules')
                      ->nullOnDelete()
                      ->after('grade_level');
            }
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropForeign(['exam_schedule_id']);
            $table->dropColumn('exam_schedule_id');
        });
    }
};
