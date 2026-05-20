<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->string('exam_seat_number')->nullable()->after('exam_schedule_id');
            $table->string('exam_room')->nullable()->after('exam_seat_number');
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn(['exam_seat_number', 'exam_room']);
        });
    }
};
