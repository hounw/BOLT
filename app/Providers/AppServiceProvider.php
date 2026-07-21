<?php

namespace App\Providers;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\KnowledgeArticle;
use App\Models\PtoPolicy;
use App\Models\PtoRequest;
use App\Models\WebhookEndpoint;
use App\Policies\AssetPolicy;
use App\Policies\AttachmentPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\KnowledgeArticlePolicy;
use App\Policies\PtoPolicyPolicy;
use App\Policies\PtoRequestPolicy;
use App\Policies\WebhookEndpointPolicy;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::authorizationView('auth.oauth-authorize');

        Passport::tokensCan([
            'employees:read' => 'Read employees and org structure.',
            'employees:write' => 'Create and update employees.',
            'hr:read' => 'Read sensitive HR records.',
            'hr:write' => 'Write sensitive HR records.',
            'pto:read' => 'Read PTO data.',
            'pto:write' => 'Create or decide PTO requests.',
            'files:read' => 'Read file metadata and downloads.',
            'files:write' => 'Upload and manage attachments.',
            'knowledge:read' => 'Read knowledge base content.',
            'knowledge:write' => 'Create and update knowledge base content.',
            'assets:read' => 'Read assets and assignments.',
            'assets:write' => 'Manage assets and assignments.',
            'audit:read' => 'Read audit logs.',
            'webhooks:write' => 'Manage webhook endpoints and replays.',
        ]);

        Passport::setDefaultScope(['employees:read', 'knowledge:read']);

        RateLimiter::for('api', function ($request) {
            [$maxAttempts, $decayMinutes] = array_pad(explode(',', config('bolt.api.rate_limit')), 2, 1);

            return Limit::perMinutes((int) $decayMinutes, (int) $maxAttempts)
                ->by($request->user()?->id ?: $request->ip());
        });

        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(PtoPolicy::class, PtoPolicyPolicy::class);
        Gate::policy(PtoRequest::class, PtoRequestPolicy::class);
        Gate::policy(Attachment::class, AttachmentPolicy::class);
        Gate::policy(KnowledgeArticle::class, KnowledgeArticlePolicy::class);
        Gate::policy(Asset::class, AssetPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(WebhookEndpoint::class, WebhookEndpointPolicy::class);

        Scramble::configure()->expose(
            ui: fn (Router $router, mixed $action) => $router->get('docs', $action)->name('scramble.docs.ui'),
            document: fn (Router $router, mixed $action) => $router->get('openapi.json', $action)->name('scramble.docs.document'),
        );

        Scramble::afterOpenApiGenerated(function (OpenApi $openApi): void {
            foreach ($openApi->paths as $path) {
                foreach ($path->operations as $method => $operation) {
                    $operationId = $this->operationIds()[$method.' '.$path->path] ?? null;

                    if ($operationId) {
                        $operation->setOperationId($operationId);
                    }
                }
            }
        });
    }

    private function operationIds(): array
    {
        return [
            'get v1/me' => 'getCurrentUser',
            'get v1/employees' => 'listEmployees',
            'post v1/employees' => 'createEmployee',
            'get v1/employees/{employee}' => 'getEmployee',
            'put v1/employees/{employee}' => 'updateEmployee',
            'get v1/departments' => 'listDepartments',
            'post v1/departments' => 'createDepartment',
            'put v1/departments/{department}' => 'updateDepartment',
            'get v1/positions' => 'listPositions',
            'post v1/positions' => 'createPosition',
            'put v1/positions/{position}' => 'updatePosition',
            'get v1/compensation-packages' => 'listCompensationPackages',
            'post v1/compensation-packages' => 'createCompensationPackage',
            'put v1/compensation-packages/{compensationPackage}' => 'updateCompensationPackage',
            'get v1/employees/{employee}/compensation-history' => 'listEmployeeCompensationHistory',
            'post v1/employees/{employee}/compensation-history' => 'createEmployeeCompensationHistory',
            'get v1/employees/{employee}/benefit-history' => 'listEmployeeBenefitHistory',
            'post v1/employees/{employee}/benefit-history' => 'createEmployeeBenefitHistory',
            'get v1/pto-policies' => 'listPtoPolicies',
            'post v1/pto-policies' => 'createPtoPolicy',
            'get v1/pto-policies/{ptoPolicy}' => 'getPtoPolicy',
            'put v1/pto-policies/{ptoPolicy}' => 'updatePtoPolicy',
            'get v1/pto-requests' => 'listPtoRequests',
            'get v1/pto-balances' => 'listPtoBalances',
            'post v1/pto-requests' => 'createPtoRequest',
            'post v1/pto-requests/{ptoRequest}/approve' => 'approvePtoRequest',
            'post v1/pto-requests/{ptoRequest}/reject' => 'rejectPtoRequest',
            'post v1/pto-requests/{ptoRequest}/cancel' => 'cancelPtoRequest',
            'get v1/knowledge-articles' => 'listKnowledgeArticles',
            'post v1/knowledge-articles' => 'createKnowledgeArticle',
            'get v1/knowledge-articles/{knowledgeArticle}' => 'getKnowledgeArticle',
            'put v1/knowledge-articles/{knowledgeArticle}' => 'updateKnowledgeArticle',
            'post v1/knowledge-articles/import' => 'importKnowledgeArticle',
            'get v1/knowledge-articles/{knowledgeArticle}/versions' => 'listKnowledgeArticleVersions',
            'get v1/knowledge-articles/{knowledgeArticle}/links' => 'listKnowledgeArticleLinks',
            'get v1/knowledge-categories' => 'listKnowledgeCategories',
            'post v1/knowledge-categories' => 'createKnowledgeCategory',
            'get v1/knowledge-categories/{knowledgeCategory}' => 'getKnowledgeCategory',
            'put v1/knowledge-categories/{knowledgeCategory}' => 'updateKnowledgeCategory',
            'delete v1/knowledge-categories/{knowledgeCategory}' => 'deleteKnowledgeCategory',
            'get v1/knowledge-categories/{knowledgeCategory}/digest' => 'getKnowledgeCategoryDigest',
            'get v1/knowledge-categories/{knowledgeCategory}/index' => 'getKnowledgeCategoryIndex',
            'get v1/knowledge-tags' => 'listKnowledgeTags',
            'get v1/assets' => 'listAssets',
            'post v1/assets' => 'createAsset',
            'get v1/assets/{asset}' => 'getAsset',
            'put v1/assets/{asset}' => 'updateAsset',
            'post v1/assets/{asset}/assign' => 'assignAsset',
            'post v1/assets/{asset}/return' => 'returnAsset',
            'get v1/assets/{asset}/history' => 'listAssetHistory',
            'post v1/assets/{asset}/history' => 'createAssetHistory',
            'post v1/attachments' => 'createAttachment',
            'get v1/attachments/{attachment}' => 'getAttachment',
            'get v1/attachments/{attachment}/download' => 'downloadAttachment',
            'get v1/audit-logs' => 'listAuditLogs',
            'get v1/webhook-events' => 'listWebhookEvents',
            'get v1/webhook-endpoints' => 'listWebhookEndpoints',
            'post v1/webhook-endpoints' => 'createWebhookEndpoint',
            'put v1/webhook-endpoints/{webhookEndpoint}' => 'updateWebhookEndpoint',
            'delete v1/webhook-endpoints/{webhookEndpoint}' => 'deleteWebhookEndpoint',
            'get v1/webhook-endpoints/{webhookEndpoint}/deliveries' => 'listWebhookDeliveries',
            'get v1/webhook-deliveries/{webhookDelivery}' => 'getWebhookDelivery',
            'post v1/webhook-endpoints/{webhookEndpoint}/test' => 'testWebhookEndpoint',
            'post v1/webhook-deliveries/{webhookDelivery}/replay' => 'replayWebhookDelivery',
        ];
    }
}
