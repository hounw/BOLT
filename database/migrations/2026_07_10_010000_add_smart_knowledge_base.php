<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 160)->unique();
            $table->foreignId('parent_id')->nullable()->constrained('knowledge_categories')->restrictOnDelete();
            $table->timestamps();

            $table->index(['parent_id', 'name']);
        });

        Schema::table('knowledge_articles', function (Blueprint $table): void {
            $table->string('excerpt', 1000)->nullable()->after('body_markdown');
            $table->foreignId('category_id')->nullable()->after('category')->constrained('knowledge_categories')->nullOnDelete();
        });

        Schema::table('knowledge_article_versions', function (Blueprint $table): void {
            $table->string('excerpt', 1000)->nullable()->after('body_markdown');
            $table->foreignId('category_id')->nullable()->after('category')->constrained('knowledge_categories')->nullOnDelete();
        });

        Schema::create('knowledge_article_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_article_id')->constrained('knowledge_articles')->cascadeOnDelete();
            $table->foreignId('target_article_id')->constrained('knowledge_articles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['source_article_id', 'target_article_id'], 'knowledge_links_source_target_unique');
            $table->index(['target_article_id', 'source_article_id'], 'knowledge_links_target_source_index');
        });

        $names = DB::table('knowledge_articles')->whereNotNull('category')->pluck('category');
        $setting = DB::table('system_settings')->where('key', 'knowledge_categories')->value('value');
        if (is_string($setting)) {
            $decoded = json_decode($setting, true);
            $names = $names->merge($decoded['value'] ?? []);
        }

        $usedSlugs = [];
        $names->map(fn ($name): string => trim(preg_replace('/\s+/', ' ', (string) $name) ?? ''))
            ->filter()
            ->unique(fn (string $name): string => mb_strtolower($name))
            ->sort()
            ->each(function (string $name) use (&$usedSlugs): void {
                $base = Str::slug($name) ?: 'category';
                $slug = $base;
                $suffix = 2;
                while (in_array($slug, $usedSlugs, true)) {
                    $slug = $base.'-'.$suffix++;
                }
                $usedSlugs[] = $slug;
                DB::table('knowledge_categories')->insert([
                    'name' => $name,
                    'slug' => $slug,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        DB::table('knowledge_categories')->orderBy('id')->each(function ($category): void {
            DB::table('knowledge_articles')
                ->whereRaw('LOWER(category) = ?', [mb_strtolower($category->name)])
                ->update(['category_id' => $category->id]);
            DB::table('knowledge_article_versions')
                ->whereRaw('LOWER(category) = ?', [mb_strtolower($category->name)])
                ->update(['category_id' => $category->id]);
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            Schema::table('knowledge_articles', function (Blueprint $table): void {
                $table->fullText(['title', 'excerpt', 'body_markdown'], 'knowledge_articles_fulltext');
            });
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            Schema::table('knowledge_articles', function (Blueprint $table): void {
                $table->dropFullText('knowledge_articles_fulltext');
            });
        }

        Schema::dropIfExists('knowledge_article_links');
        Schema::table('knowledge_article_versions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('excerpt');
        });
        Schema::table('knowledge_articles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('excerpt');
        });
        Schema::dropIfExists('knowledge_categories');
    }
};
