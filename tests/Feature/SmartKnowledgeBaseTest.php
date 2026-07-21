<?php

namespace Tests\Feature;

use App\Enums\SystemRole;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeCategory;
use App\Models\User;
use App\Services\KnowledgeArticleWriter;
use Database\Seeders\CoreAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SmartKnowledgeBaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_excerpt_is_optional_limited_versioned_and_has_a_generated_preview(): void
    {
        $owner = $this->user(SystemRole::OwnerAdmin);

        $this->actingAs($owner)->post(route('knowledge.store'), [
            'title' => 'Preview guide',
            'body_markdown' => "# Preview guide\n\nA useful fallback summary for agents.",
            'excerpt' => str_repeat('x', 301),
            'status' => 'draft',
        ])->assertSessionHasErrors('excerpt');

        $this->actingAs($owner)->post(route('knowledge.store'), [
            'title' => 'Preview guide',
            'body_markdown' => "# Preview guide\n\nA useful fallback summary for agents.",
            'status' => 'published',
        ])->assertRedirect();

        $article = KnowledgeArticle::firstOrFail();
        $this->assertNull($article->excerpt);
        $this->assertStringContainsString('useful fallback summary', $article->excerptPreview());
        $this->assertNull($article->versions()->firstOrFail()->excerpt);

        Passport::actingAs($owner, ['knowledge:read'], 'api');
        $this->getJson('/api/v1/knowledge-articles/'.$article->id)
            ->assertOk()
            ->assertJsonPath('data.excerpt', null)
            ->assertJsonPath('data.excerpt_missing', true)
            ->assertJsonPath('data.excerpt_preview', $article->excerptPreview());

        $this->actingAs($owner)->put(route('knowledge.update', $article), [
            'title' => $article->title,
            'slug' => $article->slug,
            'body_markdown' => $article->body_markdown,
            'excerpt' => str_repeat('c', 300),
            'status' => 'published',
        ])->assertRedirect();
        $this->assertSame(str_repeat('c', 300), $article->fresh()->versions()->where('version', 2)->firstOrFail()->excerpt);

        $storageCapacity = KnowledgeArticle::create([
            'title' => 'Database excerpt capacity', 'slug' => 'database-excerpt-capacity', 'body_markdown' => '# Capacity', 'excerpt' => str_repeat('d', 800), 'status' => 'draft',
        ]);
        $this->assertSame(800, mb_strlen($storageCapacity->excerpt));

    }

    public function test_categories_form_a_safe_tree_and_cannot_be_deleted_while_in_use(): void
    {
        $owner = $this->user(SystemRole::OwnerAdmin);
        $parent = KnowledgeCategory::create(['name' => 'Operations', 'slug' => 'operations']);
        $child = KnowledgeCategory::create(['name' => 'Security', 'slug' => 'security', 'parent_id' => $parent->id]);

        $this->actingAs($owner)->put(route('knowledge-taxonomy.update'), [
            'type' => 'category',
            'category_id' => $parent->id,
            'name' => $parent->name,
            'parent_id' => $child->id,
        ])->assertSessionHasErrors('parent_id');

        $article = KnowledgeArticle::create([
            'title' => 'Security guide',
            'slug' => 'security-guide',
            'body_markdown' => '# Security',
            'status' => 'published',
            'category' => $child->name,
            'category_id' => $child->id,
        ]);

        $this->actingAs($owner)->delete(route('knowledge-taxonomy.destroy'), [
            'type' => 'category',
            'category_id' => $child->id,
        ])->assertSessionHasErrors('category_id');

        $this->assertDatabaseHas('knowledge_categories', ['id' => $child->id]);
        $this->assertSame($child->id, $article->fresh()->category_id);
    }

    public function test_internal_markdown_links_create_directed_edges_and_policy_filtered_backlinks(): void
    {
        $owner = $this->user(SystemRole::OwnerAdmin);
        $reader = $this->user(SystemRole::Employee);
        $writer = app(KnowledgeArticleWriter::class);
        $published = $writer->create([
            'title' => 'Published target', 'body_markdown' => '# Target', 'status' => 'published',
        ], $owner);
        $draft = $writer->create([
            'title' => 'Draft target', 'body_markdown' => '# Draft', 'status' => 'draft',
        ], $owner);
        $source = $writer->create([
            'title' => 'Source article',
            'body_markdown' => "[Published](/knowledge/{$published->id})\n[Again](/knowledge/{$published->id})\n[Draft](/knowledge/{$draft->id})",
            'status' => 'published',
        ], $owner);

        $this->assertEqualsCanonicalizing([$published->id, $draft->id], $source->outgoingLinks()->pluck('knowledge_articles.id')->all());
        $this->assertSame([$source->id], $published->incomingLinks()->pluck('knowledge_articles.id')->all());

        $this->actingAs($owner)->getJson(route('knowledge.link-search', ['q' => 'Published', 'exclude' => $source->id]))
            ->assertOk()
            ->assertJsonPath('data.0.markdown', '[Published target](/knowledge/'.$published->id.')');

        Passport::actingAs($reader, ['knowledge:read'], 'api');
        $this->getJson('/api/v1/knowledge-articles/'.$source->id.'/links?direction=outgoing')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id);
        $this->getJson('/api/v1/knowledge-articles/'.$published->id.'/links?direction=incoming')
            ->assertOk()
            ->assertJsonPath('data.0.id', $source->id);
    }

    public function test_category_digest_index_search_and_missing_excerpt_filters_are_agent_friendly(): void
    {
        $reader = $this->user(SystemRole::Employee);
        $root = KnowledgeCategory::create(['name' => 'Company', 'slug' => 'company']);
        $child = KnowledgeCategory::create(['name' => 'Operations', 'slug' => 'operations', 'parent_id' => $root->id]);
        KnowledgeArticle::create([
            'title' => 'Direct company guide', 'slug' => 'direct-company-guide', 'body_markdown' => '# Direct', 'excerpt' => 'Company summary', 'status' => 'published', 'category' => $root->name, 'category_id' => $root->id,
        ]);
        KnowledgeArticle::create([
            'title' => 'Operational handbook', 'slug' => 'operational-handbook', 'body_markdown' => '# Handbook searchable phrase', 'status' => 'published', 'category' => $child->name, 'category_id' => $child->id,
        ]);
        KnowledgeArticle::create([
            'title' => 'Private operations draft', 'slug' => 'private-operations-draft', 'body_markdown' => '# Private', 'status' => 'draft', 'category' => $child->name, 'category_id' => $child->id,
        ]);

        Passport::actingAs($reader, ['knowledge:read'], 'api');
        $this->getJson('/api/v1/knowledge-categories/'.$root->id.'/digest')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.excerpt', 'Company summary');
        $this->getJson('/api/v1/knowledge-categories/'.$root->id.'/index')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.path', 'Company / Operations')->assertJsonCount(1, 'data.0.article_previews');
        $this->getJson('/api/v1/knowledge-articles?q=searchable&missing_excerpt=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Operational handbook');
    }

    public function test_category_api_management_requires_write_scope_and_audits_mutations(): void
    {
        $owner = $this->user(SystemRole::OwnerAdmin);
        Passport::actingAs($owner, ['knowledge:read'], 'api');
        $this->postJson('/api/v1/knowledge-categories', ['name' => 'Finance'])->assertForbidden();

        Passport::actingAs($owner, ['knowledge:read', 'knowledge:write'], 'api');
        $response = $this->withHeader('Idempotency-Key', 'category-finance')
            ->postJson('/api/v1/knowledge-categories', ['name' => 'Finance'])
            ->assertSuccessful()
            ->assertJsonPath('data.name', 'Finance');

        $this->assertDatabaseHas('audit_logs', ['event' => 'knowledge_category.created']);
        $this->getJson('/api/v1/knowledge-tags')->assertOk();
        $this->getJson('/api/v1/knowledge-categories/'.$response->json('data.id'))->assertOk();
    }

    private function user(SystemRole $role): User
    {
        $this->seed(CoreAccessSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }
}
