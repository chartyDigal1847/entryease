<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SECURITY: Create applicants table with security best practices
     * 
     * Features:
     * 1. DEORIS user ID reference (users managed in DEORIS database)
     * 2. Enum status field (whitelist validation at DB level)
     * 3. Admin-only fields (admin_notes, reviewed_by)
     * 4. Audit trail with timestamps
     * 5. Indexes for query performance
     */
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table) {
            // SECURITY: Primary key
            $table->id();

            // SECURITY: DEORIS user ID (references users in DEORIS database)
            // - No foreign key constraint since users are in a different database
            // - Application-level validation ensures integrity
            $table->unsignedBigInteger('deoris_user_id')->nullable();

            // SECURITY: Grade level - no quotes or special chars allowed per Form Request validation
            $table->string('grade_level');

            // SECURITY: Application status - enum field with whitelist values
            // Database-level constraint prevents invalid statuses
            // Only admins can change via separate endpoints (removed from $fillable)
            $table->enum('status', ['Pending', 'Under Review', 'Approved', 'Rejected'])
                  ->default('Pending');

            // SECURITY: Additional information from student - sanitized via Form Request
            $table->text('additional_info')->nullable();

            // SECURITY: Admin notes - only settable by admins
            // Removed from $fillable to prevent student manipulation
            $table->text('admin_notes')->nullable();

            // SECURITY: Tracks which admin reviewed the application
            // For accountability and audit purposes
            $table->unsignedBigInteger('reviewed_by')->nullable();

            // SECURITY: Audit trail
            $table->timestamps();

            // SECURITY: Indexes for common queries
            $table->index('deoris_user_id');
            $table->index('status');
            $table->index('created_at'); // For date range queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applicants');
    }
};
