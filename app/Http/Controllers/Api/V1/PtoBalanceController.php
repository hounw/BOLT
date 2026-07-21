<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PtoBalanceResource;
use App\Models\PtoBalance;
use App\Models\PtoRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PtoBalanceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PtoRequest::class);

        $query = PtoBalance::query()
            ->with(['employee', 'policy'])
            ->visibleToUser($request->user());

        return PtoBalanceResource::collection($query->latest('period_start')->paginate());
    }
}
