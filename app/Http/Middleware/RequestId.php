<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->header('X-Request-Id') ?: 'req_'.Str::ulid();

        $request->headers->set('X-Request-Id', $id);
        Log::withContext(['request_id' => $id]);

        return $next($request)->header('X-Request-Id', $id);
    }
}