<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()->can('api.clients.manage'), 403);

        return view('web.settings.edit', [
            'mainCurrency' => SystemSetting::mainCurrency(),
            'webhookDeliveryHistoryLimit' => SystemSetting::integer(SystemSetting::WEBHOOK_DELIVERY_HISTORY_LIMIT, 10000),
            'queueWorkerCount' => SystemSetting::integer(SystemSetting::QUEUE_WORKER_COUNT, 1),
        ]);
    }

    public function update(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()->can('api.clients.manage'), 403);

        $data = $request->validate([
            'main_currency' => ['required', 'string', 'size:3'],
            'webhook_delivery_history_limit' => ['required', 'integer', 'min:0', 'max:1000000'],
            'queue_worker_count' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        SystemSetting::putString(SystemSetting::MAIN_CURRENCY, strtoupper($data['main_currency']));
        SystemSetting::putInteger(SystemSetting::WEBHOOK_DELIVERY_HISTORY_LIMIT, (int) $data['webhook_delivery_history_limit']);
        SystemSetting::putInteger(SystemSetting::QUEUE_WORKER_COUNT, (int) $data['queue_worker_count']);

        $auditLogger->log('system_settings.updated', metadata: [
            'keys' => [SystemSetting::MAIN_CURRENCY, SystemSetting::WEBHOOK_DELIVERY_HISTORY_LIMIT, SystemSetting::QUEUE_WORKER_COUNT],
        ]);

        return redirect()->route('settings.edit')->with('status', 'Settings updated.');
    }
}
