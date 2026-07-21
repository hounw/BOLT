<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PtoPolicyRequest;
use App\Http\Resources\PtoPolicyResource;
use App\Models\PtoPolicy;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PtoPolicyController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PtoPolicy::class);

        return PtoPolicyResource::collection(
            PtoPolicy::query()
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->paginate()
        );
    }

    public function store(PtoPolicyRequest $request, WebhookDispatcher $webhooks): PtoPolicyResource
    {
        $this->authorize('create', PtoPolicy::class);

        $policy = DB::transaction(function () use ($request): PtoPolicy {
            if ($request->boolean('is_default')) {
                PtoPolicy::query()->update(['is_default' => false]);
            }

            return PtoPolicy::create($request->validated() + [
                'is_default' => $request->boolean('is_default'),
            ]);
        });

        $webhooks->dispatch('pto_policy.created', ['pto_policy_id' => $policy->id]);

        return new PtoPolicyResource($policy);
    }

    public function show(PtoPolicy $ptoPolicy): PtoPolicyResource
    {
        $this->authorize('view', $ptoPolicy);

        return new PtoPolicyResource($ptoPolicy);
    }

    public function update(PtoPolicyRequest $request, PtoPolicy $ptoPolicy, WebhookDispatcher $webhooks): PtoPolicyResource
    {
        $this->authorize('update', $ptoPolicy);

        DB::transaction(function () use ($request, $ptoPolicy): void {
            if ($request->boolean('is_default')) {
                PtoPolicy::query()
                    ->whereKeyNot($ptoPolicy->id)
                    ->update(['is_default' => false]);
            }

            $ptoPolicy->update($request->validated() + [
                'is_default' => $request->boolean('is_default'),
            ]);
        });

        $webhooks->dispatch('pto_policy.updated', ['pto_policy_id' => $ptoPolicy->id]);

        return new PtoPolicyResource($ptoPolicy->refresh());
    }
}
