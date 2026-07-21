<?php

namespace Tests\Feature;

use Tests\TestCase;

class DocumentationWorkflowTest extends TestCase
{
    public function test_living_docs_and_agent_files_exist(): void
    {
        foreach ([
            'product-definition.md',
            'technical-definition.md',
            'project-journal.md',
            'USING-BOLT.md',
            'LOCAL-DEVELOPMENT.md',
            'AGENTS.md',
            'CLAUDE.md',
            'GEMINI.md',
            'production-runbook.md',
            'docs/release-checklist.md',
            'docs/upgrade-recipes/README.md',
            'CHANGELOG.md',
            'github-public-repo.md',
            'ai/api-guide.md',
            '.env.example',
        ] as $path) {
            $this->assertFileExists(base_path($path));
        }

        $agents = file_get_contents(base_path('AGENTS.md'));

        $this->assertStringContainsString('product-definition.md', $agents);
        $this->assertStringContainsString('technical-definition.md', $agents);
        $this->assertStringContainsString('USING-BOLT.md', $agents);
        $this->assertStringContainsString('LOCAL-DEVELOPMENT.md', $agents);
        $this->assertStringContainsString('ai/api-guide.md', $agents);
        $this->assertStringContainsString('/openapi.json', $agents);

        $adoptionGuide = file_get_contents(base_path('USING-BOLT.md'));
        $this->assertStringContainsString('independent private repository', $adoptionGuide);
        $this->assertStringContainsString('`origin`', $adoptionGuide);
        $this->assertStringContainsString('`upstream`', $adoptionGuide);

        $localGuide = file_get_contents(base_path('LOCAL-DEVELOPMENT.md'));
        $this->assertStringContainsString('http://127.0.0.1:8000', $localGuide);
        $this->assertStringContainsString('composer run setup', $localGuide);
        $this->assertStringContainsString('composer run dev', $localGuide);
        $this->assertStringContainsString('bolt:create-local-admin', $localGuide);
        $this->assertStringContainsString('Leave the development process running', $localGuide);

        $composer = file_get_contents(base_path('composer.json'));
        $this->assertStringContainsString("touch('database/database.sqlite')", $composer);
        $this->assertStringContainsString('serve --host=127.0.0.1 --port=8000', $composer);

        $environmentExample = file_get_contents(base_path('.env.example'));
        $this->assertStringContainsString('APP_URL=http://127.0.0.1:8000', $environmentExample);
        $this->assertStringContainsString('DB_CONNECTION=sqlite', $environmentExample);
        $this->assertStringContainsString('DB_DATABASE=database/database.sqlite', $environmentExample);

        $runbook = file_get_contents(base_path('production-runbook.md'));
        $this->assertStringContainsString('APP_ENV=production', $runbook);
        $this->assertStringContainsString('APP_DEBUG=false', $runbook);
        $this->assertStringContainsString('Pre-Migration Backup', $runbook);
        $this->assertStringContainsString('Rollback And Restore', $runbook);
        $this->assertStringContainsString('cPanel is optional', $runbook);
        $this->assertStringContainsString('~/.ssh/deploy/bolt/cpanel_ed25519', $runbook);
        $this->assertStringContainsString('~/.ssh/deploy/bolt/github_ed25519', $runbook);
        $this->assertStringContainsString('ssh-keygen -lf', $runbook);
        $this->assertStringContainsString('Allow write access', $runbook);
        $this->assertStringContainsString('technical-definition.md', $runbook);

        $this->assertStringContainsString('public fingerprints', $agents);
        $this->assertStringContainsString('never overwrite or reuse an existing key', $agents);

        $apiGuide = file_get_contents(base_path('ai/api-guide.md'));
        $this->assertStringContainsString('passport:client --public', $apiGuide);
        $this->assertStringContainsString('code_verifier', $apiGuide);
    }

    public function test_api_docs_routes_are_exposed(): void
    {
        $this->get('/docs')->assertOk();

        $this->get('/openapi.json')
            ->assertOk()
            ->assertJsonPath('info.title', 'BOLT API');
    }

    public function test_openapi_uses_stable_agent_friendly_operation_ids(): void
    {
        $spec = $this->get('/openapi.json')
            ->assertOk()
            ->json();

        $this->assertSame('listEmployees', $spec['paths']['/v1/employees']['get']['operationId']);
        $this->assertSame('createPtoRequest', $spec['paths']['/v1/pto-requests']['post']['operationId']);
        $this->assertSame('listWebhookEvents', $spec['paths']['/v1/webhook-events']['get']['operationId']);
        $this->assertSame('importKnowledgeArticle', $spec['paths']['/v1/knowledge-articles/import']['post']['operationId']);
        $this->assertSame('listKnowledgeArticleVersions', $spec['paths']['/v1/knowledge-articles/{knowledgeArticle}/versions']['get']['operationId']);

        $operationIds = collect($spec['paths'])
            ->flatMap(fn (array $path): array => collect($path)
                ->filter(fn (array $operation, string $method): bool => in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true))
                ->pluck('operationId')
                ->all())
            ->values();

        $this->assertNotContains(null, $operationIds);
        $this->assertCount($operationIds->count(), $operationIds->unique());
    }
}
