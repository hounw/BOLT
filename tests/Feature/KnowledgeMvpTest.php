<?php

namespace Tests\Feature;

use App\Enums\SystemRole;
use App\Models\Attachment;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeCategory;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\MarkdownRenderer;
use Database\Seeders\CoreAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class KnowledgeMvpTest extends TestCase
{
    use RefreshDatabase;

    public function test_markdown_import_creates_editable_article_source_attachment_snapshot_and_collision_safe_slug(): void
    {
        Storage::fake('local');
        $owner = $this->user(SystemRole::OwnerAdmin);
        $markdown = "# Remote Work Guide\n\n## Setup\n\nUse the VPN.";

        $this->actingAs($owner)
            ->post(route('knowledge.store'), [
                'source_markdown' => UploadedFile::fake()->createWithContent('remote-work.md', $markdown),
                'status' => 'draft',
            ])
            ->assertRedirect();

        $first = KnowledgeArticle::firstOrFail();
        $this->assertSame('Remote Work Guide', $first->title);
        $this->assertSame('remote-work-guide', $first->slug);
        $this->assertSame($markdown, $first->body_markdown);
        $this->assertCount(1, $first->versions);
        $this->assertSame(1, $first->versions->first()->version);

        $attachment = $first->attachments()->firstOrFail();
        $this->assertSame('knowledge_source', $attachment->metadata['kind']);
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'knowledge_article.imported',
            'auditable_id' => $first->id,
        ]);

        $this->actingAs($owner)
            ->post(route('knowledge.store'), [
                'source_markdown' => UploadedFile::fake()->createWithContent('duplicate.md', $markdown),
                'status' => 'draft',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('knowledge_articles', ['slug' => 'remote-work-guide-2']);

        $this->actingAs($owner)
            ->post(route('knowledge.store'), [
                'source_markdown' => UploadedFile::fake()->createWithContent('plain-guide.md', 'Intro without a heading.'),
                'status' => 'draft',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('knowledge_articles', [
            'title' => 'Plain Guide',
            'slug' => 'plain-guide',
        ]);
    }

    public function test_markdown_import_rejects_invalid_extension_and_preserves_submitted_content(): void
    {
        $owner = $this->user(SystemRole::OwnerAdmin);

        $this->actingAs($owner)
            ->from(route('knowledge.create'))
            ->post(route('knowledge.store'), [
                'source_markdown' => UploadedFile::fake()->createWithContent('guide.html', '<h1>Unsafe</h1>'),
                'title' => 'Preserved title',
                'body_markdown' => '# Preserved body',
                'status' => 'draft',
            ])
            ->assertRedirect(route('knowledge.create'))
            ->assertSessionHasErrors('source_markdown')
            ->assertSessionHasInput('title', 'Preserved title')
            ->assertSessionHasInput('body_markdown', '# Preserved body');

        $this->assertDatabaseCount('knowledge_articles', 0);
    }

    public function test_renderer_supports_gfm_and_removes_raw_html_and_unsafe_links(): void
    {
        $rendered = app(MarkdownRenderer::class)->render(<<<'MD'
# Guide

## Checklist

- [x] Complete

| Name | State |
| --- | --- |
| VPN | Ready |

```php
echo 'safe';
```

<script>alert('no')</script>

[Unsafe](javascript:alert('no'))
MD);

        $this->assertStringContainsString('<table>', $rendered['html']);
        $this->assertStringContainsString('type="checkbox"', $rendered['html']);
        $this->assertStringContainsString('<pre><code class="language-php">', $rendered['html']);
        $this->assertStringNotContainsString('<script', $rendered['html']);
        $this->assertStringNotContainsString('javascript:', $rendered['html']);
        $this->assertSame('Checklist', $rendered['headings'][0]['label']);
    }

    public function test_preview_and_reading_view_use_secure_rendered_markdown(): void
    {
        $owner = $this->user(SystemRole::OwnerAdmin);
        $markdown = "# Guide\n\n## Steps\n\n**Bold**\n\n## Notes\n\n<script>bad()</script>";
        $article = KnowledgeArticle::create([
            'title' => 'Rendered guide',
            'slug' => 'rendered-guide',
            'body_markdown' => $markdown,
            'status' => 'published',
            'created_by_id' => $owner->id,
            'updated_by_id' => $owner->id,
        ]);

        $preview = $this->actingAs($owner)
            ->postJson(route('knowledge.preview'), ['body_markdown' => $markdown])
            ->assertOk()
            ->assertJsonPath('data.headings.0.label', 'Steps');

        $this->assertStringContainsString('<strong>Bold</strong>', $preview->json('data.html'));
        $this->assertStringNotContainsString('<script', $preview->json('data.html'));

        $this->actingAs($owner)
            ->get(route('knowledge.show', $article))
            ->assertOk()
            ->assertSee('<strong>Bold</strong>', false)
            ->assertSee('On this page')
            ->assertDontSee('bad()');
    }

    public function test_editor_includes_accessible_markdown_cheatsheet(): void
    {
        $owner = $this->user(SystemRole::OwnerAdmin);

        $this->actingAs($owner)
            ->get(route('knowledge.create'))
            ->assertOk()
            ->assertSee('Open Markdown syntax help')
            ->assertSee('Markdown cheatsheet')
            ->assertSee('Task list')
            ->assertSee('| Name | Status |');
    }

    public function test_owner_admin_can_manage_knowledge_categories_and_individual_tags(): void
    {
        $owner = $this->user(SystemRole::OwnerAdmin);
        $article = KnowledgeArticle::create([
            'title' => 'Taxonomy article',
            'slug' => 'taxonomy-article',
            'body_markdown' => '# Taxonomy',
            'status' => 'published',
            'category' => 'Operations',
            'category_id' => KnowledgeCategory::create(['name' => 'Operations', 'slug' => 'operations'])->id,
            'tags' => ['tag1, tag2', 'tag3'],
        ]);

        $this->actingAs($owner)
            ->get(route('knowledge-taxonomy.index'))
            ->assertOk()
            ->assertSee('Knowledge setup')
            ->assertSee('tag1')
            ->assertSee('tag2')
            ->assertDontSee('tag1, tag2');

        $this->actingAs($owner)
            ->post(route('knowledge-taxonomy.store'), ['type' => 'category', 'name' => 'Finance'])
            ->assertRedirect();
        $this->actingAs($owner)
            ->post(route('knowledge-taxonomy.store'), ['type' => 'tag', 'name' => 'Important'])
            ->assertRedirect();

        $this->assertDatabaseHas('knowledge_categories', ['name' => 'Finance']);
        $this->assertSame(['Important'], SystemSetting::array(SystemSetting::KNOWLEDGE_TAGS));

        $operations = KnowledgeCategory::where('name', 'Operations')->firstOrFail();
        $this->actingAs($owner)
            ->put(route('knowledge-taxonomy.update'), [
                'type' => 'category',
                'category_id' => $operations->id,
                'name' => 'Operations & Process',
            ])
            ->assertRedirect();
        $this->actingAs($owner)
            ->put(route('knowledge-taxonomy.update'), [
                'type' => 'tag',
                'current_name' => 'tag1',
                'name' => 'Primary',
            ])
            ->assertRedirect();

        $article->refresh();
        $this->assertSame('Operations & Process', $article->category);
        $this->assertSame(['Primary', 'tag2', 'tag3'], $article->tags);

        $this->actingAs($owner)
            ->delete(route('knowledge-taxonomy.destroy'), [
                'type' => 'tag',
                'current_name' => 'tag2',
            ])
            ->assertRedirect();

        $this->assertSame(['Primary', 'tag3'], $article->fresh()->tags);
        $this->assertDatabaseHas('audit_logs', ['event' => 'knowledge_taxonomy.renamed']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'knowledge_taxonomy.deleted']);
    }

    public function test_article_form_uses_searchable_category_and_removable_tag_comboboxes(): void
    {
        $owner = $this->user(SystemRole::OwnerAdmin);
        $finance = KnowledgeCategory::create(['name' => 'Finance', 'slug' => 'finance']);
        KnowledgeCategory::create(['name' => 'Operations', 'slug' => 'operations']);
        SystemSetting::putArray(SystemSetting::KNOWLEDGE_TAGS, ['tag1', 'tag2']);

        $this->actingAs($owner)
            ->get(route('knowledge.create'))
            ->assertOk()
            ->assertSee('data-single-combobox', false)
            ->assertSee('id="knowledge-category-search"', false)
            ->assertDontSee('datalist id="knowledge-categories"', false)
            ->assertSee('data-multi-combobox', false)
            ->assertSee('data-value="tag1"', false)
            ->assertSee('data-value="tag2"', false)
            ->assertDontSee('type="checkbox" name="tags[]"', false);

        $this->actingAs($owner)
            ->post(route('knowledge.store'), [
                'title' => 'Classified article',
                'body_markdown' => '# Classified',
                'status' => 'draft',
                'category_id' => $finance->id,
                'tags' => ['tag1', 'tag2'],
            ])
            ->assertRedirect();

        $article = KnowledgeArticle::where('title', 'Classified article')->firstOrFail();
        $this->assertSame('Finance', $article->category);
        $this->assertSame($finance->id, $article->category_id);
        $this->assertSame(['tag1', 'tag2'], $article->tags);
    }

    public function test_employee_cannot_manage_knowledge_taxonomy(): void
    {
        $employee = $this->user(SystemRole::Employee);

        $this->actingAs($employee)
            ->get(route('knowledge-taxonomy.index'))
            ->assertForbidden();
    }

    public function test_readers_only_see_published_articles_and_cannot_download_draft_attachments(): void
    {
        Storage::fake('local');
        $reader = $this->user(SystemRole::Employee);
        $reader->givePermissionTo('files.manage');
        $published = KnowledgeArticle::create([
            'title' => 'Published SOP',
            'slug' => 'published-sop',
            'body_markdown' => '# Published',
            'status' => 'published',
        ]);
        $draft = KnowledgeArticle::create([
            'title' => 'Private draft',
            'slug' => 'private-draft',
            'body_markdown' => '# Draft',
            'status' => 'draft',
        ]);
        Storage::disk('local')->put('attachments/draft.md', '# Private');
        $attachment = Attachment::create([
            'attachable_type' => $draft->getMorphClass(),
            'attachable_id' => $draft->id,
            'disk' => 'local',
            'path' => 'attachments/draft.md',
            'original_name' => 'draft.md',
            'size' => 9,
        ]);

        $this->actingAs($reader)
            ->get(route('knowledge.index'))
            ->assertOk()
            ->assertSee($published->title)
            ->assertDontSee($draft->title)
            ->assertDontSee('Any status');
        $this->actingAs($reader)->get(route('knowledge.show', $draft))->assertForbidden();
        $this->actingAs($reader)->get(route('attachments.download', $attachment))->assertForbidden();

        Passport::actingAs($reader, ['knowledge:read'], 'api');
        $this->getJson('/api/v1/knowledge-articles')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Published SOP')
            ->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/knowledge-articles/{$draft->id}")->assertForbidden();
    }

    public function test_updates_create_snapshots_and_restore_loads_old_content_before_saving_new_version(): void
    {
        $owner = $this->user(SystemRole::OwnerAdmin);

        $this->actingAs($owner)
            ->post(route('knowledge.store'), [
                'title' => 'Versioned SOP',
                'body_markdown' => '# Version one',
                'status' => 'published',
                'category' => 'Operations',
                'tags' => 'sop, operations',
            ])
            ->assertRedirect();
        $article = KnowledgeArticle::firstOrFail();

        $this->actingAs($owner)
            ->put(route('knowledge.update', $article), [
                'title' => 'Versioned SOP',
                'slug' => $article->slug,
                'body_markdown' => '# Version two',
                'status' => 'published',
                'category' => 'Operations',
                'tags' => 'sop, operations',
            ])
            ->assertRedirect(route('knowledge.show', $article));

        $article->refresh();
        $this->assertSame(2, $article->version);
        $this->assertSame([2, 1], $article->versions()->pluck('version')->all());

        $versionOne = $article->versions()->where('version', 1)->firstOrFail();
        $this->actingAs($owner)
            ->get(route('knowledge.versions.restore', [$article, $versionOne]))
            ->assertOk()
            ->assertSee('# Version one')
            ->assertSee('Save as new version');

        $this->actingAs($owner)
            ->put(route('knowledge.update', $article), [
                'title' => $versionOne->title,
                'slug' => $article->slug,
                'body_markdown' => $versionOne->body_markdown,
                'status' => 'published',
                'category' => $versionOne->category,
                'tags' => implode(', ', $versionOne->tags ?? []),
                'restored_from_version' => 1,
            ])
            ->assertRedirect(route('knowledge.show', $article));

        $this->assertSame(3, $article->fresh()->version);
        $this->assertDatabaseHas('knowledge_article_versions', [
            'knowledge_article_id' => $article->id,
            'version' => 3,
            'body_markdown' => '# Version one',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'knowledge_article.version_restored',
            'auditable_id' => $article->id,
        ]);

        $this->actingAs($owner)
            ->put(route('knowledge.update', $article), [
                'title' => $article->title,
                'slug' => $article->slug,
                'body_markdown' => $article->fresh()->body_markdown,
                'status' => 'archived',
            ])
            ->assertRedirect(route('knowledge.show', $article));
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'knowledge_article.archived',
            'auditable_id' => $article->id,
        ]);

        $this->actingAs($owner)
            ->put(route('knowledge.update', $article), [
                'title' => $article->title,
                'slug' => $article->slug,
                'body_markdown' => $article->fresh()->body_markdown,
                'status' => 'published',
            ])
            ->assertRedirect(route('knowledge.show', $article));
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'knowledge_article.published',
            'auditable_id' => $article->id,
        ]);
    }

    public function test_api_import_retains_markdown_and_exposes_attachment_and_version_metadata(): void
    {
        Storage::fake('local');
        $owner = $this->user(SystemRole::OwnerAdmin);
        Passport::actingAs($owner, ['knowledge:read', 'knowledge:write'], 'api');

        $response = $this->withHeader('Idempotency-Key', 'knowledge-import-1')
            ->post('/api/v1/knowledge-articles/import', [
                'file' => UploadedFile::fake()->createWithContent('security.md', "# Security SOP\n\nRotate keys."),
                'status' => 'published',
                'tags' => ['security', 'sop'],
            ], ['Accept' => 'application/json'])
            ->assertSuccessful()
            ->assertJsonPath('data.title', 'Security SOP')
            ->assertJsonPath('data.body_markdown', "# Security SOP\n\nRotate keys.")
            ->assertJsonPath('data.attachments_count', 1)
            ->assertJsonPath('data.versions_count', 1)
            ->assertJsonPath('data.attachments.0.metadata.kind', 'knowledge_source');

        $articleId = $response->json('data.id');
        $this->getJson("/api/v1/knowledge-articles/{$articleId}/versions")
            ->assertOk()
            ->assertJsonPath('data.0.version', 1)
            ->assertJsonPath('data.0.editor.id', $owner->id);
    }

    private function user(SystemRole $role): User
    {
        $this->seed(CoreAccessSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }
}
