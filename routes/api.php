<?php

use App\Http\Controllers\Api\V1\AssetController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\BenefitHistoryController;
use App\Http\Controllers\Api\V1\CompensationHistoryController;
use App\Http\Controllers\Api\V1\CompensationPackageController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\KnowledgeArticleController;
use App\Http\Controllers\Api\V1\KnowledgeCategoryController;
use App\Http\Controllers\Api\V1\KnowledgeTagController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PositionController;
use App\Http\Controllers\Api\V1\PtoBalanceController;
use App\Http\Controllers\Api\V1\PtoPolicyController;
use App\Http\Controllers\Api\V1\PtoRequestController;
use App\Http\Controllers\Api\V1\WebhookEndpointController;
use App\Http\Middleware\EnsureIdempotencyKey;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['auth:api', 'throttle:api'])
    ->name('api.v1.')
    ->group(function (): void {
        Route::get('me', MeController::class)->name('me');

        Route::apiResource('employees', EmployeeController::class)
            ->only(['index', 'show'])
            ->middleware('scope:employees:read');
        Route::match(['put', 'patch'], 'employees/{employee}', [EmployeeController::class, 'update'])
            ->middleware('scope:employees:write')
            ->name('employees.update');
        Route::post('employees', [EmployeeController::class, 'store'])
            ->middleware(['scope:employees:write', EnsureIdempotencyKey::class])
            ->name('employees.store');
        Route::get('employees/{employee}/compensation-history', [CompensationHistoryController::class, 'index'])
            ->middleware('scope:hr:read')
            ->name('employees.compensation.index');
        Route::post('employees/{employee}/compensation-history', [CompensationHistoryController::class, 'store'])
            ->middleware(['scope:hr:write', EnsureIdempotencyKey::class])
            ->name('employees.compensation.store');
        Route::get('employees/{employee}/benefit-history', [BenefitHistoryController::class, 'index'])
            ->middleware('scope:hr:read')
            ->name('employees.benefits.index');
        Route::post('employees/{employee}/benefit-history', [BenefitHistoryController::class, 'store'])
            ->middleware(['scope:hr:write', EnsureIdempotencyKey::class])
            ->name('employees.benefits.store');
        Route::get('departments', [DepartmentController::class, 'index'])
            ->middleware('scope:employees:read')
            ->name('departments.index');
        Route::post('departments', [DepartmentController::class, 'store'])
            ->middleware(['scope:employees:write', EnsureIdempotencyKey::class])
            ->name('departments.store');
        Route::match(['put', 'patch'], 'departments/{department}', [DepartmentController::class, 'update'])
            ->middleware('scope:employees:write')
            ->name('departments.update');
        Route::get('positions', [PositionController::class, 'index'])
            ->middleware('scope:employees:read')
            ->name('positions.index');
        Route::post('positions', [PositionController::class, 'store'])
            ->middleware(['scope:employees:write', EnsureIdempotencyKey::class])
            ->name('positions.store');
        Route::match(['put', 'patch'], 'positions/{position}', [PositionController::class, 'update'])
            ->middleware('scope:employees:write')
            ->name('positions.update');
        Route::get('compensation-packages', [CompensationPackageController::class, 'index'])
            ->middleware('scope:hr:read')
            ->name('compensation-packages.index');
        Route::post('compensation-packages', [CompensationPackageController::class, 'store'])
            ->middleware(['scope:hr:write', EnsureIdempotencyKey::class])
            ->name('compensation-packages.store');
        Route::match(['put', 'patch'], 'compensation-packages/{compensationPackage}', [CompensationPackageController::class, 'update'])
            ->middleware('scope:hr:write')
            ->name('compensation-packages.update');

        Route::apiResource('pto-policies', PtoPolicyController::class)
            ->parameters(['pto-policies' => 'ptoPolicy'])
            ->only(['index', 'show'])
            ->middleware('scope:pto:read');
        Route::match(['put', 'patch'], 'pto-policies/{ptoPolicy}', [PtoPolicyController::class, 'update'])
            ->middleware('scope:pto:write')
            ->name('pto-policies.update');
        Route::post('pto-policies', [PtoPolicyController::class, 'store'])
            ->middleware(['scope:pto:write', EnsureIdempotencyKey::class])
            ->name('pto-policies.store');
        Route::get('pto-requests', [PtoRequestController::class, 'index'])
            ->middleware('scope:pto:read')
            ->name('pto-requests.index');
        Route::get('pto-balances', [PtoBalanceController::class, 'index'])
            ->middleware('scope:pto:read')
            ->name('pto-balances.index');
        Route::post('pto-requests', [PtoRequestController::class, 'store'])
            ->middleware(['scope:pto:write', EnsureIdempotencyKey::class])
            ->name('pto-requests.store');
        Route::post('pto-requests/{ptoRequest}/approve', [PtoRequestController::class, 'approve'])
            ->middleware(['scope:pto:write', EnsureIdempotencyKey::class])
            ->name('pto-requests.approve');
        Route::post('pto-requests/{ptoRequest}/reject', [PtoRequestController::class, 'reject'])
            ->middleware(['scope:pto:write', EnsureIdempotencyKey::class])
            ->name('pto-requests.reject');
        Route::post('pto-requests/{ptoRequest}/cancel', [PtoRequestController::class, 'cancel'])
            ->middleware(['scope:pto:write', EnsureIdempotencyKey::class])
            ->name('pto-requests.cancel');

        Route::post('knowledge-articles/import', [KnowledgeArticleController::class, 'import'])
            ->middleware(['scope:knowledge:write', EnsureIdempotencyKey::class])
            ->name('knowledge-articles.import');
        Route::get('knowledge-articles/{knowledgeArticle}/links', [KnowledgeArticleController::class, 'links'])
            ->middleware('scope:knowledge:read')
            ->name('knowledge-articles.links');
        Route::get('knowledge-articles/{knowledgeArticle}/versions', [KnowledgeArticleController::class, 'versions'])
            ->middleware('scope:knowledge:read')
            ->name('knowledge-articles.versions');
        Route::apiResource('knowledge-articles', KnowledgeArticleController::class)
            ->parameters(['knowledge-articles' => 'knowledgeArticle'])
            ->only(['index', 'show'])
            ->middleware('scope:knowledge:read');
        Route::match(['put', 'patch'], 'knowledge-articles/{knowledgeArticle}', [KnowledgeArticleController::class, 'update'])
            ->middleware('scope:knowledge:write')
            ->name('knowledge-articles.update');
        Route::post('knowledge-articles', [KnowledgeArticleController::class, 'store'])
            ->middleware(['scope:knowledge:write', EnsureIdempotencyKey::class])
            ->name('knowledge-articles.store');

        Route::get('knowledge-categories/{knowledgeCategory}/digest', [KnowledgeCategoryController::class, 'digest'])
            ->middleware('scope:knowledge:read')
            ->name('knowledge-categories.digest');
        Route::get('knowledge-categories/{knowledgeCategory}/index', [KnowledgeCategoryController::class, 'categoryIndex'])
            ->middleware('scope:knowledge:read')
            ->name('knowledge-categories.index-summary');
        Route::apiResource('knowledge-categories', KnowledgeCategoryController::class)
            ->parameters(['knowledge-categories' => 'knowledgeCategory'])
            ->only(['index', 'show'])
            ->middleware('scope:knowledge:read');
        Route::post('knowledge-categories', [KnowledgeCategoryController::class, 'store'])
            ->middleware(['scope:knowledge:write', EnsureIdempotencyKey::class])
            ->name('knowledge-categories.store');
        Route::match(['put', 'patch'], 'knowledge-categories/{knowledgeCategory}', [KnowledgeCategoryController::class, 'update'])
            ->middleware('scope:knowledge:write')
            ->name('knowledge-categories.update');
        Route::delete('knowledge-categories/{knowledgeCategory}', [KnowledgeCategoryController::class, 'destroy'])
            ->middleware('scope:knowledge:write')
            ->name('knowledge-categories.destroy');
        Route::get('knowledge-tags', [KnowledgeTagController::class, 'index'])
            ->middleware('scope:knowledge:read')
            ->name('knowledge-tags.index');

        Route::apiResource('assets', AssetController::class)
            ->only(['index', 'show'])
            ->middleware('scope:assets:read');
        Route::match(['put', 'patch'], 'assets/{asset}', [AssetController::class, 'update'])
            ->middleware('scope:assets:write')
            ->name('assets.update');
        Route::post('assets', [AssetController::class, 'store'])
            ->middleware(['scope:assets:write', EnsureIdempotencyKey::class])
            ->name('assets.store');
        Route::post('assets/{asset}/assign', [AssetController::class, 'assign'])
            ->middleware(['scope:assets:write', EnsureIdempotencyKey::class])
            ->name('assets.assign');
        Route::post('assets/{asset}/return', [AssetController::class, 'return'])
            ->middleware(['scope:assets:write', EnsureIdempotencyKey::class])
            ->name('assets.return');
        Route::get('assets/{asset}/history', [AssetController::class, 'history'])
            ->middleware('scope:assets:read')
            ->name('assets.history');
        Route::post('assets/{asset}/history', [AssetController::class, 'storeHistory'])
            ->middleware(['scope:assets:write', EnsureIdempotencyKey::class])
            ->name('assets.history.store');

        Route::post('attachments', [AttachmentController::class, 'store'])
            ->middleware(['scope:files:write', EnsureIdempotencyKey::class])
            ->name('attachments.store');
        Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])
            ->middleware('scope:files:read')
            ->name('attachments.show');
        Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])
            ->middleware('scope:files:read')
            ->name('attachments.download');

        Route::get('audit-logs', [AuditLogController::class, 'index'])
            ->middleware('scope:audit:read')
            ->name('audit-logs.index');

        Route::get('webhook-events', [WebhookEndpointController::class, 'events'])
            ->middleware('scope:webhooks:write')
            ->name('webhook-events.index');
        Route::apiResource('webhook-endpoints', WebhookEndpointController::class)
            ->parameters(['webhook-endpoints' => 'webhookEndpoint'])
            ->only(['index', 'update', 'destroy'])
            ->middleware('scope:webhooks:write');
        Route::post('webhook-endpoints', [WebhookEndpointController::class, 'store'])
            ->middleware(['scope:webhooks:write', EnsureIdempotencyKey::class])
            ->name('webhook-endpoints.store');
        Route::get('webhook-endpoints/{webhookEndpoint}/deliveries', [WebhookEndpointController::class, 'deliveries'])
            ->middleware('scope:webhooks:write')
            ->name('webhook-endpoints.deliveries');
        Route::get('webhook-deliveries/{webhookDelivery}', [WebhookEndpointController::class, 'delivery'])
            ->middleware('scope:webhooks:write')
            ->name('webhook-deliveries.show');
        Route::post('webhook-endpoints/{webhookEndpoint}/test', [WebhookEndpointController::class, 'test'])
            ->middleware(['scope:webhooks:write', EnsureIdempotencyKey::class])
            ->name('webhook-endpoints.test');
        Route::post('webhook-deliveries/{webhookDelivery}/replay', [WebhookEndpointController::class, 'replay'])
            ->middleware(['scope:webhooks:write', EnsureIdempotencyKey::class])
            ->name('webhook-deliveries.replay');
    });
