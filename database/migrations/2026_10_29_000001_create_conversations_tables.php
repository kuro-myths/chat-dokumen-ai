<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Riwayat percakapan chat — dulu hanya di localStorage browser.
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('provider')->nullable();
            $table->unsignedInteger('msg_count')->default(0);
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role');                      // user | assistant
            $table->string('type')->default('text');     // text | image | video
            $table->mediumText('media_url')->nullable(); // URL/data gambar atau video
            $table->longText('content')->nullable();
            $table->timestamps();
        });

        // Refresh model default Gemini yang tersimpan lama: keluarga 2.0/2.5
        // sudah menua — sesuai modul praktikum 2026 model stabil gemini-3.8-flash.
        if (Schema::hasTable('ai_settings')) {
            DB::table('ai_settings')
                ->where('slug', 'gemini')
                ->where(function ($q) {
                    $q->whereIn('model', ['', 'gemini-2.0-flash', 'gemini-2.5-flash'])
                        ->orWhereNull('model');
                })
                ->update(['model' => 'gemini-3.8-flash']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
