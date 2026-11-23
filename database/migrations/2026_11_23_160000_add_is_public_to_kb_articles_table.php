<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kb_articles', function (Blueprint $table) {
            // Internal by default: an article only reaches the employees when someone decides so.
            $table->boolean('is_public')->default(false)->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('kb_articles', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
    }
};
