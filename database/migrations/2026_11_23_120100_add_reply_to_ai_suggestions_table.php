<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_suggestions', function (Blueprint $table) {
            // The draft reply proposed to the technician, if any.
            $table->text('reply')->nullable()->after('confidence');
            // proposed, no_article or failed; empty while nothing was attempted.
            $table->string('reply_status')->nullable()->after('reply');
        });

        // The knowledge base articles a draft reply relies on (its sources).
        Schema::create('ai_suggestion_kb_article', function (Blueprint $table) {
            $table->foreignId('ai_suggestion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kb_article_id')->constrained()->cascadeOnDelete();

            $table->primary(['ai_suggestion_id', 'kb_article_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_suggestion_kb_article');

        Schema::table('ai_suggestions', function (Blueprint $table) {
            $table->dropColumn(['reply', 'reply_status']);
        });
    }
};
