<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links a ticket to earlier open tickets that seem to describe the same problem.
     */
    public function up(): void
    {
        Schema::create('ticket_duplicates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('duplicate_of_id')->constrained('tickets')->cascadeOnDelete();
            // "ai" when the AI confirmed it, "keywords" when only shared words suggest it.
            $table->string('detected_by');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['ticket_id', 'duplicate_of_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_duplicates');
    }
};
