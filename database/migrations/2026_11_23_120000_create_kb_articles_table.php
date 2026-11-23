<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            // Only published articles are searched and shown to technicians.
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            // Full-text search exists on MySQL and MariaDB only; SQLite (tests) uses a keyword fallback.
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                $table->fullText(['title', 'content']);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_articles');
    }
};
