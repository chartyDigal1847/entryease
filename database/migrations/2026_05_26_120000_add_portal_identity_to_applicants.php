<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->string('portal_student_email', 150)->nullable()->after('deoris_user_id');
            $table->string('portal_student_name', 200)->nullable()->after('portal_student_email');
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn(['portal_student_email', 'portal_student_name']);
        });
    }
};
