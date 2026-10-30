<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('title');
            $table->enum('type', ['skripsi', 'proposal'])->default('skripsi');
            $table->string('status')->default('uploaded'); // uploaded|imported|reviewing|reviewed|review_failed|drafting
            $table->string('source_path')->nullable();
            $table->json('review_summary')->nullable();
            $table->timestamps();
        });

        Schema::create('document_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('index');
            $table->string('heading')->nullable();
            $table->unsignedTinyInteger('level')->nullable();
            $table->longText('body');
            $table->timestamps();
            $table->unique(['document_id', 'index']);
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->json('sections');
            $table->timestamps();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_section_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('highlight_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->string('author_name')->default('Anonim');
            $table->boolean('is_ai')->default(false);
            $table->text('body');
            $table->boolean('resolved')->default(false);
            $table->timestamps();
        });

        Schema::create('highlights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_section_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('paragraph_index')->nullable();
            $table->string('quote');
            $table->string('type')->default('style');   // grammar|citation|methodology|structure|style
            $table->string('severity')->default('low'); // high|medium|low
            $table->string('color')->default('green');  // red|yellow|blue|purple|green
            $table->text('comment')->nullable();
            $table->text('suggestion')->nullable();
            $table->text('replacement')->nullable();
            $table->string('status')->default('pending'); // pending|accepted|rejected
            $table->timestamps();
        });

        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('driver'); // openai|openai-compatible|anthropic|gemini|ollama
            $table->string('base_url')->nullable();
            $table->string('model')->nullable();
            $table->text('api_key')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'ai_settings', 'highlights', 'comments',
            'document_versions', 'document_sections', 'documents',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
