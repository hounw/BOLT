<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WebhookDeliveryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\WebhookEndpointRequest;
use App\Http\Resources\WebhookDeliveryResource;
use App\Http\Resources\WebhookEndpointResource;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class WebhookEndpointController extends Controller
{
    public function events(): array
    {
        $this->authorize('viewAny', WebhookEndpoint::class);

        return [
            'data' => collect(config('bolt.webhooks.event_catalog'))
                ->map(fn (string $description, string $name): array => [
                    'name' => $name,
                    'description' => $description,
                ])
                ->values()
                ->prepend([
                    'name' => '*',
                    'description' => 'Subscribe to every webhook event.',
                ])
                ->all(),
        ];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WebhookEndpoint::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', Rule::in(['1', '0'])],
            'event' => ['nullable', 'string', Rule::in(array_merge(config('bolt.webhooks.events'), ['*']))],
        ]);

        return WebhookEndpointResource::collection(
            WebhookEndpoint::query()
                ->search($filters['q'] ?? null)
                ->filterActive($filters['is_active'] ?? null)
                ->subscribedTo($filters['event'] ?? null)
                ->latest()
                ->paginate()
                ->withQueryString()
        );
    }

    public function store(WebhookEndpointRequest $request): WebhookEndpointResource
    {
        $this->authorize('create', WebhookEndpoint::class);

        return new WebhookEndpointResource(WebhookEndpoint::create($request->validated() + [
            'is_active' => $request->boolean('is_active', true),
        ]));
    }

    public function update(WebhookEndpointRequest $request, WebhookEndpoint $webhookEndpoint): WebhookEndpointResource
    {
        $this->authorize('update', $webhookEndpoint);

        $payload = collect($request->validated())
            ->reject(fn ($value, $key): bool => $key === 'secret' && blank($value))
            ->filter(fn ($value) => $value !== null)
            ->all();
        $webhookEndpoint->update($payload);

        return new WebhookEndpointResource($webhookEndpoint);
    }

    public function destroy(WebhookEndpoint $webhookEndpoint): array
    {
        $this->authorize('delete', $webhookEndpoint);

        $webhookEndpoint->delete();

        return ['data' => ['deleted' => true]];
    }

    public function deliveries(Request $request, WebhookEndpoint $webhookEndpoint): AnonymousResourceCollection
    {
        $this->authorize('view', $webhookEndpoint);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(WebhookDeliveryStatus::class)],
            'event' => ['nullable', 'string', 'max:120'],
            'created_from' => ['nullable', 'date'],
            'created_until' => ['nullable', 'date', 'after_or_equal:created_from'],
        ]);

        return WebhookDeliveryResource::collection(
            $webhookEndpoint->deliveries()
                ->filterStatus($filters['status'] ?? null)
                ->forEvent($filters['event'] ?? null)
                ->createdOnOrAfter($filters['created_from'] ?? null)
                ->createdOnOrBefore($filters['created_until'] ?? null)
                ->latest()
                ->paginate()
                ->withQueryString()
        );
    }

    public function delivery(WebhookDelivery $webhookDelivery): WebhookDeliveryResource
    {
        $this->authorize('view', $webhookDelivery->endpoint);

        return new WebhookDeliveryResource($webhookDelivery);
    }

    public function test(WebhookEndpoint $webhookEndpoint, WebhookDispatcher $webhooks): WebhookDeliveryResource
    {
        $this->authorize('update', $webhookEndpoint);

        abort_unless($webhookEndpoint->is_active, 422, 'Reactivate the endpoint before sending a test delivery.');

        return new WebhookDeliveryResource($webhooks->test($webhookEndpoint));
    }

    public function replay(WebhookDelivery $webhookDelivery, WebhookDispatcher $webhooks): WebhookDeliveryResource
    {
        $this->authorize('update', $webhookDelivery->endpoint);

        abort_unless($webhookDelivery->endpoint?->is_active, 422, 'Reactivate the endpoint before replaying deliveries.');

        return new WebhookDeliveryResource($webhooks->replay($webhookDelivery));
    }
}
