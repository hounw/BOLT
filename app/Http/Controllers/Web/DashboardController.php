<?php

namespace App\Http\Controllers\Web;

use App\Enums\PtoRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\KnowledgeArticle;
use App\Models\PtoRequest;
use App\Models\WebhookDelivery;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'counts' => [
                'employees' => Employee::count(),
                'pto_pending' => PtoRequest::where('status', PtoRequestStatus::Pending)->count(),
                'knowledge' => KnowledgeArticle::count(),
                'assets' => Asset::count(),
            ],
            'recentAudits' => AuditLog::latest('occurred_at')->limit(8)->get(),
            'failedWebhooks' => WebhookDelivery::where('status', 'failed')->latest()->limit(5)->get(),
        ]);
    }
}
