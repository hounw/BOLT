<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_article_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_article_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('title');
            $table->longText('body_markdown');
            $table->string('status')->index();
            $table->string('category')->nullable();
            $table->json('tags')->nullable();
            $table->foreignId('edited_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['knowledge_article_id', 'version']);
        });

        DB::table('knowledge_articles')
            ->orderBy('id')
            ->chunkById(100, function ($articles): void {
                DB::table('knowledge_article_versions')->insert(
                    $articles->map(fn ($article): array => [
                        'knowledge_article_id' => $article->id,
                        'version' => $article->version,
                        'title' => $article->title,
                        'body_markdown' => $article->body_markdown,
                        'status' => $article->status,
                        'category' => $article->category,
                        'tags' => $article->tags,
                        'edited_by_id' => $article->updated_by_id ?? $article->created_by_id,
                        'created_at' => $article->updated_at ?? now(),
                        'updated_at' => $article->updated_at ?? now(),
                    ])->all(),
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_article_versions');
    }
};
