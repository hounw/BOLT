<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotencyKey
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('post')) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || trim($key) === '') {
            return response()->json([
                'error' => [
                    'code' => 'idempotency_key_required',
                    'message' => 'An Idempotency-Key header is required for this operation.',
                ],
            ], 428);
        }

        $fingerprint = $this->fingerprint($request);
        $userId = $request->user()?->id;
        $record = IdempotencyKey::query()
            ->where('key', $key)
            ->where('user_id', $userId)
            ->first();

        if ($record && $record->request_hash !== $fingerprint) {
            return response()->json([
                'error' => [
                    'code' => 'idempotency_key_conflict',
                    'message' => 'This Idempotency-Key was already used with a different request payload.',
                ],
            ], 409);
        }

        if ($record && $record->response_body !== null) {
            return response()->json($record->response_body, $record->status_code ?? 200);
        }

        $record ??= IdempotencyKey::create([
            'key' => $key,
            'user_id' => $userId,
            'method' => $request->method(),
            'route' => $request->route()?->getName(),
            'request_hash' => $fingerprint,
            'locked_at' => now(),
            'expires_at' => now()->addHours((int) config('bolt.api.idempotency_ttl_hours')),
        ]);

        $response = $next($request);

        if ($response instanceof JsonResponse && $response->getStatusCode() < 500) {
            $record->forceFill([
                'response_body' => $response->getData(true),
                'status_code' => $response->getStatusCode(),
            ])->save();
        }

        return $response;
    }

    private function fingerprint(Request $request): string
    {
        $payload = [
            'method' => $request->method(),
            'route' => $request->route()?->getName(),
            'input' => $this->normalize($request->input()),
            'files' => $this->normalize($request->allFiles()),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            $path = $value->getRealPath();

            return [
                'original_name' => $value->getClientOriginalName(),
                'mime_type' => $value->getClientMimeType(),
                'size' => $value->getSize(),
                'sha256' => $path && is_file($path) ? hash_file('sha256', $path) : null,
            ];
        }

        if (is_array($value)) {
            ksort($value);

            return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
        }

        return $value;
    }
}
