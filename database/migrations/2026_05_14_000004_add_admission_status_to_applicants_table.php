<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            if (!Schema::hasColumn('applicants', 'admission_status')) {
                $table->string('admission_status', 30)
                      ->default('pending')
                      ->after('status')
                      ->index();
            }
        });

        DB::table('applicants')->where('status', 'Approved')->update(['admission_status' => 'approved']);
        DB::table('applicants')->where('status', 'Rejected')->update(['admission_status' => 'rejected']);
        DB::table('applicants')->where('status', 'Under Review')->update(['admission_status' => 'under_review']);
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            if (Schema::hasColumn('applicants', 'admission_status')) {
                $table->dropColumn('admission_status');
            }
        });
    }
};
