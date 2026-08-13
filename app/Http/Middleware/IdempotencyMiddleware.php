<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }

        $idempotencyKey = $request->header('X-Idempotency-Key');
        if (! $idempotencyKey) {
            return $next($request);
        }

        $userProfile = $request->attributes->get('user_profile');
        $userId = $userProfile ? $userProfile->id : 'anonymous';
        $cacheKey = "idempotency:{$userId}:{$idempotencyKey}";

        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);

            return response(
                $cached['content'],
                $cached['status'],
                array_merge($cached['headers'], ['X-Cache-Idempotency' => 'HIT'])
            );
        }

        $lockKey = "idempotency_lock:{$userId}:{$idempotencyKey}";
        $lock = Cache::lock($lockKey, 10);

        if (! $lock->get()) {
            return response()->json([
                'error' => 'Conflict',
                'message' => 'A request with the same idempotency key is already in progress',
            ], 409);
        }

        try {
            $response = $next($request);

            if (in_array($response->status(), [200, 201, 202, 204])) {
                Cache::put($cacheKey, [
                    'content' => $response->getContent(),
                    'status' => $response->status(),
                    'headers' => $this->filterHeaders($response->headers->all()),
                ], 86400); // 24 hours cache
            }
        } finally {
            $lock->release();
        }

        return $response;
    }

    private function filterHeaders(array $headers): array
    {
        unset($headers['set-cookie'], $headers['Set-Cookie']);

        return array_map(fn ($val) => is_array($val) ? implode(', ', $val) : $val, $headers);
    }
}
