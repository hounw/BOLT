<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;

class ApiTokenController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAccess($request);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'status' => ['nullable', Rule::in(['active', 'revoked'])],
            'scope' => ['nullable', 'string', Rule::in(Passport::scopeIds())],
        ]);

        $tokens = Passport::token()
            ->newQuery()
            ->when($filters['q'] ?? null, function ($query, string $term): void {
                $needle = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';
                $matchingUserIds = User::query()
                    ->where('name', 'like', $needle)
                    ->orWhere('email', 'like', $needle)
                    ->pluck('id');

                $query->where(function ($query) use ($needle, $matchingUserIds): void {
                    $query->where('name', 'like', $needle)
                        ->orWhereIn('user_id', $matchingUserIds);
                });
            })
            ->when($filters['user_id'] ?? null, fn ($query, string $userId) => $query->where('user_id', (int) $userId))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('revoked', false))
            ->when(($filters['status'] ?? null) === 'revoked', fn ($query) => $query->where('revoked', true))
            ->when($filters['scope'] ?? null, fn ($query, string $scope) => $query->whereJsonContains('scopes', $scope))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('web.access.tokens.index', [
            'tokenUsers' => User::query()->whereIn('id', $tokens->getCollection()->pluck('user_id')->filter())->get()->keyBy('id'),
            'tokens' => $tokens,
            'filters' => $filters,
            'scopes' => Passport::scopes(),
            'users' => User::query()->orderBy('name')->get(),
            'plainToken' => session('plain_api_token'),
        ]);
    }

    public function store(Request $request, ClientRepository $clients, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorizeAccess($request);

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'name' => ['required', 'string', 'max:120'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', Rule::in(Passport::scopeIds())],
        ]);

        $this->ensurePersonalAccessClient($clients);

        $user = User::findOrFail($data['user_id']);
        $scopes = collect($data['scopes'])->unique()->values()->all();
        $result = $user->createToken($data['name'], $scopes);
        $token = $result->getToken();

        $auditLogger->log('api_token.created', $token, $request->user(), newValues: [
            'token_id' => $token?->id,
            'name' => $data['name'],
            'user_id' => $user->id,
            'scopes' => $scopes,
        ]);

        return redirect()
            ->route('access.tokens.index')
            ->with('status', 'API token created. Copy it now; it will only be shown once.')
            ->with('plain_api_token', $result->accessToken);
    }

    public function revoke(Request $request, Token $token, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorizeAccess($request);

        if (! $token->revoked) {
            $token->revoke();
        }

        $auditLogger->log('api_token.revoked', $token, $request->user(), newValues: [
            'token_id' => $token->id,
            'name' => $token->name,
            'user_id' => $token->user_id,
            'scopes' => $token->scopes,
        ]);

        return redirect()->route('access.tokens.index')->with('status', 'API token revoked.');
    }

    private function authorizeAccess(Request $request): void
    {
        abort_unless($request->user()?->can(PermissionName::ApiClientsManage->value), 403);
    }

    private function ensurePersonalAccessClient(ClientRepository $clients): void
    {
        try {
            $clients->personalAccessClient(config('auth.guards.api.provider'));
        } catch (\RuntimeException) {
            $clients->createPersonalAccessGrantClient('BOLT Personal Access Tokens', config('auth.guards.api.provider'));
        }
    }
}
