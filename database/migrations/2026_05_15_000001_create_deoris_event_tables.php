<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deoris_processed_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('event', 100);
            $table->string('source', 50);
            $table->string('correlation_id', 64)->nullable()->index();
            $table->timestamp('processed_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['event', 'source']);
        });

        Schema::create('deoris_event_outbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('event', 100);
            $table->string('status', 20)->default('pending');
            $table->json('payload');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deoris_event_outbox');
        Schema::dropIfExists('deoris_processed_events');
    }
};
