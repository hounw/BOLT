<?php

namespace App\Http\Controllers\Web;

use App\Enums\WebhookDeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\WebhookDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WebhookEndpointController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', WebhookEndpoint::class);

        $filters = request()->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', Rule::in(['1', '0'])],
            'event' => ['nullable', 'string', Rule::in(array_merge(config('bolt.webhooks.events'), ['*']))],
        ]);

        return view('web.webhooks.index', [
            'endpoints' => WebhookEndpoint::withCount('deliveries')
                ->search($filters['q'] ?? null)
                ->filterActive($filters['is_active'] ?? null)
                ->subscribedTo($filters['event'] ?? null)
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'events' => $this->eventCatalog(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', WebhookEndpoint::class);

        return view('web.webhooks.form', [
            'endpoint' => new WebhookEndpoint,
            'events' => $this->eventCatalog(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', WebhookEndpoint::class);

        $endpoint = WebhookEndpoint::create($this->validated($request) + [
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('webhooks.show', $endpoint)->with('status', 'Webhook endpoint created.');
    }

    public function show(WebhookEndpoint $webhookEndpoint): View
    {
        $this->authorize('view', $webhookEndpoint);

        $filters = request()->validate([
            'status' => ['nullable', Rule::enum(WebhookDeliveryStatus::class)],
            'event' => ['nullable', 'string', 'max:120'],
            'created_from' => ['nullable', 'date'],
            'created_until' => ['nullable', 'date', 'after_or_equal:created_from'],
        ]);

        return view('web.webhooks.show', [
            'endpoint' => $webhookEndpoint,
            'deliveries' => $webhookEndpoint->deliveries()
                ->filterStatus($filters['status'] ?? null)
                ->forEvent($filters['event'] ?? null)
                ->createdOnOrAfter($filters['created_from'] ?? null)
                ->createdOnOrBefore($filters['created_until'] ?? null)
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'retryEligibleCount' => $webhookEndpoint->deliveries()
                ->where('status', WebhookDeliveryStatus::Failed)
                ->whereNotNull('next_attempt_at')
                ->where('next_attempt_at', '<=', now())
                ->where('attempts', '<', config('bolt.webhooks.max_attempts'))
                ->count(),
            'deliveryEvents' => $webhookEndpoint->deliveries()->distinct()->orderBy('event')->pluck('event'),
            'deliveryStatuses' => WebhookDeliveryStatus::cases(),
            'filters' => $filters,
        ]);
    }

    public function delivery(WebhookDelivery $webhookDelivery): View
    {
        $webhookDelivery->load('endpoint');

        $this->authorize('view', $webhookDelivery->endpoint);

        return view('web.webhooks.delivery', [
            'delivery' => $webhookDelivery,
        ]);
    }

    public function edit(WebhookEndpoint $webhookEndpoint): View
    {
        $this->authorize('update', $webhookEndpoint);

        return view('web.webhooks.form', [
            'endpoint' => $webhookEndpoint,
            'events' => $this->eventCatalog(),
        ]);
    }

    public function update(Request $request, WebhookEndpoint $webhookEndpoint): RedirectResponse
    {
        $this->authorize('update', $webhookEndpoint);

        $data = $this->validated($request, $webhookEndpoint);

        if (blank($data['secret'] ?? null)) {
            unset($data['secret']);
        }

        $webhookEndpoint->update($data + [
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('webhooks.show', $webhookEndpoint)->with('status', 'Webhook endpoint updated.');
    }

    public function test(WebhookEndpoint $webhookEndpoint, WebhookDispatcher $webhooks): RedirectResponse
    {
        $this->authorize('update', $webhookEndpoint);

        abort_unless($webhookEndpoint->is_active, 422, 'Reactivate the endpoint before sending a test delivery.');

        $webhooks->test($webhookEndpoint);

        return redirect()->route('webhooks.show', $webhookEndpoint)->with('status', 'Webhook test delivery queued.');
    }

    public function replay(WebhookDelivery $webhookDelivery, WebhookDispatcher $webhooks): RedirectResponse
    {
        $this->authorize('update', $webhookDelivery->endpoint);

        abort_unless($webhookDelivery->endpoint?->is_active, 422, 'Reactivate the endpoint before replaying deliveries.');

        $webhooks->replay($webhookDelivery);

        return redirect()->route('webhooks.show', $webhookDelivery->endpoint)->with('status', 'Webhook delivery replay queued.');
    }

    private function validated(Request $request, ?WebhookEndpoint $endpoint = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url', 'max:2048'],
            'secret' => [$endpoint?->exists ? 'nullable' : 'required', 'string', 'min:24'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(array_merge(config('bolt.webhooks.events'), ['*']))],
        ]);
    }

    private function eventCatalog(): array
    {
        return ['*' => 'Subscribe to every webhook event.'] + config('bolt.webhooks.event_catalog');
    }
}
