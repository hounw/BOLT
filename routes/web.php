<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Web\AccessUserController;
use App\Http\Controllers\Web\AccountController;
use App\Http\Controllers\Web\ApiTokenController;
use App\Http\Controllers\Web\AssetController;
use App\Http\Controllers\Web\AssetTagController;
use App\Http\Controllers\Web\AttachmentController;
use App\Http\Controllers\Web\AuditLogController;
use App\Http\Controllers\Web\CompensationPackageController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DepartmentController;
use App\Http\Controllers\Web\EmployeeController;
use App\Http\Controllers\Web\KnowledgeArticleController;
use App\Http\Controllers\Web\KnowledgeCategoryController;
use App\Http\Controllers\Web\KnowledgeTaxonomyController;
use App\Http\Controllers\Web\PositionController;
use App\Http\Controllers\Web\PtoPolicyController;
use App\Http\Controllers\Web\PtoRequestController;
use App\Http\Controllers\Web\SystemSettingsController;
use App\Http\Controllers\Web\WebhookEndpointController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('account/password', [AccountController::class, 'password'])->name('account.password');
    Route::put('account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');

    Route::get('employees/chart', [EmployeeController::class, 'chart'])->name('employees.chart');
    Route::get('employees/{employee}/photo', [EmployeeController::class, 'photo'])->name('employees.photo');
    Route::resource('employees', EmployeeController::class)->except(['destroy']);
    Route::get('departments/chart', [DepartmentController::class, 'chart'])->name('departments.chart');
    Route::resource('departments', DepartmentController::class)->only(['index', 'store', 'update']);
    Route::resource('positions', PositionController::class)->only(['index', 'store', 'update']);
    Route::resource('compensation-packages', CompensationPackageController::class)->only(['index', 'store', 'update']);
    Route::post('employees/{employee}/compensation-history', [EmployeeController::class, 'storeCompensation'])->name('employees.compensation.store');
    Route::post('employees/{employee}/benefit-history', [EmployeeController::class, 'storeBenefit'])->name('employees.benefits.store');
    Route::post('attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::post('knowledge/preview', [KnowledgeArticleController::class, 'preview'])->name('knowledge.preview');
    Route::get('knowledge/link-search', [KnowledgeArticleController::class, 'linkSearch'])->name('knowledge.link-search');
    Route::get('knowledge-categories', [KnowledgeCategoryController::class, 'index'])->name('knowledge-categories.index');
    Route::get('knowledge-categories/{knowledgeCategory}', [KnowledgeCategoryController::class, 'show'])->name('knowledge-categories.show');
    Route::get('knowledge/{knowledgeArticle}/versions', [KnowledgeArticleController::class, 'versions'])->name('knowledge.versions');
    Route::get('knowledge/{knowledgeArticle}/versions/{knowledgeArticleVersion}/restore', [KnowledgeArticleController::class, 'restore'])->name('knowledge.versions.restore');
    Route::resource('knowledge', KnowledgeArticleController::class)
        ->parameters(['knowledge' => 'knowledgeArticle'])
        ->except(['destroy']);
    Route::get('knowledge-setup', [KnowledgeTaxonomyController::class, 'index'])->name('knowledge-taxonomy.index');
    Route::post('knowledge-setup', [KnowledgeTaxonomyController::class, 'store'])->name('knowledge-taxonomy.store');
    Route::put('knowledge-setup', [KnowledgeTaxonomyController::class, 'update'])->name('knowledge-taxonomy.update');
    Route::delete('knowledge-setup', [KnowledgeTaxonomyController::class, 'destroy'])->name('knowledge-taxonomy.destroy');
    Route::resource('assets', AssetController::class)->except(['destroy']);
    Route::get('asset-tags', [AssetTagController::class, 'index'])->name('asset-tags.index');
    Route::post('asset-tags', [AssetTagController::class, 'store'])->name('asset-tags.store');
    Route::put('asset-tags', [AssetTagController::class, 'update'])->name('asset-tags.update');
    Route::delete('asset-tags', [AssetTagController::class, 'destroy'])->name('asset-tags.destroy');
    Route::get('assets/{asset}/photo', [AssetController::class, 'photo'])->name('assets.photo');
    Route::post('assets/{asset}/events', [AssetController::class, 'storeEvent'])->name('assets.events.store');
    Route::post('assets/{asset}/assign', [AssetController::class, 'assign'])->name('assets.assign');
    Route::post('assets/{asset}/return', [AssetController::class, 'return'])->name('assets.return');
    Route::resource('pto-policies', PtoPolicyController::class)->except(['show', 'destroy']);
    Route::get('pto', [PtoRequestController::class, 'index'])->name('pto.index');
    Route::get('pto/create', [PtoRequestController::class, 'create'])->name('pto.create');
    Route::post('pto', [PtoRequestController::class, 'store'])->name('pto.store');
    Route::post('pto/adjustments', [PtoRequestController::class, 'adjust'])->name('pto.adjustments.store');
    Route::post('pto/{ptoRequest}/approve', [PtoRequestController::class, 'approve'])->name('pto.approve');
    Route::post('pto/{ptoRequest}/reject', [PtoRequestController::class, 'reject'])->name('pto.reject');
    Route::post('pto/{ptoRequest}/cancel', [PtoRequestController::class, 'cancel'])->name('pto.cancel');
    Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
    Route::get('access/users', [AccessUserController::class, 'index'])->name('access.users.index');
    Route::get('access/users/create', [AccessUserController::class, 'create'])->name('access.users.create');
    Route::post('access/users', [AccessUserController::class, 'store'])->name('access.users.store');
    Route::get('access/users/{user}/edit', [AccessUserController::class, 'edit'])->name('access.users.edit');
    Route::put('access/users/{user}', [AccessUserController::class, 'update'])->name('access.users.update');
    Route::get('access/tokens', [ApiTokenController::class, 'index'])->name('access.tokens.index');
    Route::post('access/tokens', [ApiTokenController::class, 'store'])->name('access.tokens.store');
    Route::put('access/tokens/{token}/revoke', [ApiTokenController::class, 'revoke'])->name('access.tokens.revoke');
    Route::get('settings', [SystemSettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SystemSettingsController::class, 'update'])->name('settings.update');
    Route::resource('webhooks', WebhookEndpointController::class)
        ->parameters(['webhooks' => 'webhookEndpoint'])
        ->except(['destroy']);
    Route::get('webhook-deliveries/{webhookDelivery}', [WebhookEndpointController::class, 'delivery'])->name('webhook-deliveries.show');
    Route::post('webhooks/{webhookEndpoint}/test', [WebhookEndpointController::class, 'test'])->name('webhooks.test');
    Route::post('webhook-deliveries/{webhookDelivery}/replay', [WebhookEndpointController::class, 'replay'])->name('webhook-deliveries.replay');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
