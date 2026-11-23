<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // The opinion of the requester once the ticket is resolved: 1 (very unhappy) to 5 (very happy).
            $table->unsignedTinyInteger('satisfaction_score')->nullable()->after('closed_at');
            $table->text('satisfaction_comment')->nullable()->after('satisfaction_score');
            $table->timestamp('rated_at')->nullable()->after('satisfaction_comment');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['satisfaction_score', 'satisfaction_comment', 'rated_at']);
        });
    }
};
