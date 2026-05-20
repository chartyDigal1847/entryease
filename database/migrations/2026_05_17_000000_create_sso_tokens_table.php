<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sso_tokens', function (Blueprint $table) {
            // Token string as primary key (short-lived, single-use artifact)
            $table->string('token')->primary()->comment('Single-use SSO token from portal');

            // User identity (from portal)
            $table->string('sso_id')->comment('User ID from DEORIS portal');
            $table->string('sso_role')->comment('User role (admin, admission_officer, student)');
            $table->string('sso_name')->comment('User full name from portal');
            $table->string('sso_email')->comment('User email from portal');

            // Portal validation
            $table->text('portal_signature')->comment('Portal-signed JWT or signature of user identity');
            $table->dateTime('portal_issued_at')->comment('Timestamp when portal issued the token');

            // Exchange tracking (single-use enforcement)
            $table->dateTime('exchanged_at')->nullable()->comment('When token was exchanged for session (null = not yet used)');

            // Standard timestamps
            $table->timestamps();

            // Indexes for efficient lookups and cleanup
            $table->index('sso_id');
            $table->index('portal_issued_at');
            $table->index('exchanged_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sso_tokens');
    }
};
