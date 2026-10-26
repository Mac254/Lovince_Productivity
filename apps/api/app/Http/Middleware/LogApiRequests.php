<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * One log line per API request: method, path, status, duration and user.
 * Bodies, query strings and headers are never logged.
 */
class LogApiRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $started = hrtime(true);

        $response = $next($request);

        if (config('focus.log_requests')) {
            $status = $response->getStatusCode();
            $level = match (true) {
                $status >= 500 => 'error',
                $status >= 400 && ! in_array($status, [401, 422], true) => 'warning',
                default => 'info',
            };

            Log::log($level, sprintf('%s /%s %d', $request->method(), $request->path(), $status), [
                'ms' => round((hrtime(true) - $started) / 1e6, 1),
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
            ]);
        }

        return $response;
    }
}
