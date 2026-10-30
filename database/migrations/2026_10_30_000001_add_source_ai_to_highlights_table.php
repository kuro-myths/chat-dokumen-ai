<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lacak AI mana yang memberi setiap temuan: provider (gemini, manual,
     * 9router, dst.) + model yang benar-benar menjawab saat review.
     * Dipakai frontend sebagai badge "oleh <model>" di kartu temuan.
     */
    public function up(): void
    {
        Schema::table('highlights', function (Blueprint $table) {
            $table->string('source_provider')->nullable()->after('status');
            $table->string('source_model')->nullable()->after('source_provider');
        });
    }

    public function down(): void
    {
        Schema::table('highlights', function (Blueprint $table) {
            $table->dropColumn(['source_provider', 'source_model']);
        });
    }
};
