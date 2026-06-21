<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyIotApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $secret = config('services.iot.api_key');

        // Jika secret belum dikonfigurasi di .env, tolak semua request
        if (empty($secret)) {
            return response()->json(['error' => 'IoT API key not configured'], 500);
        }

        $provided = $request->header('X-Device-Key')
            ?? $request->header('Authorization')
            ?? $request->input('api_key');

        // Strip "Bearer " prefix jika ada
        if (str_starts_with((string) $provided, 'Bearer ')) {
            $provided = substr($provided, 7);
        }

        if (!$provided || !hash_equals($secret, $provided)) {
            return response()->json([
                'error' => 'Unauthorized. Sertakan X-Device-Key header.',
            ], 401);
        }

        return $next($request);
    }
}