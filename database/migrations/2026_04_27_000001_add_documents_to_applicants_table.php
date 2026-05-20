<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add document fields to applicants table
     * These are "coming soon" features that admins can view
     */
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            // SECURITY: Store relative file path to 2x2 photo
            // Marked as coming soon - students will upload via separate feature
            if (!Schema::hasColumn('applicants', 'photo_2x2')) {
                $table->string('photo_2x2')->nullable()->comment('2x2 Photo - Coming Soon');
            }

            // SECURITY: Store relative file path to PSA Birth Certificate
            // Marked as coming soon - students will upload via separate feature
            if (!Schema::hasColumn('applicants', 'psa_birth_cert')) {
                $table->string('psa_birth_cert')->nullable()->comment('PSA Birth Certificate - Coming Soon');
            }

            // Track when documents were last updated (for audit purposes)
            if (!Schema::hasColumn('applicants', 'documents_updated_at')) {
                $table->timestamp('documents_updated_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            if (Schema::hasColumn('applicants', 'photo_2x2')) {
                $table->dropColumn('photo_2x2');
            }
            if (Schema::hasColumn('applicants', 'psa_birth_cert')) {
                $table->dropColumn('psa_birth_cert');
            }
            if (Schema::hasColumn('applicants', 'documents_updated_at')) {
                $table->dropColumn('documents_updated_at');
            }
        });
    }
};
