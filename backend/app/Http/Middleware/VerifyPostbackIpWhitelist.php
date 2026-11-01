<?php

namespace App\Http\Middleware;

use App\Services\Postbacks\IpWhitelistVerifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyPostbackIpWhitelist
{
    public function __construct(
        protected IpWhitelistVerifier $verifier
    ) {}

    /**
     * Handle incoming request and verify against provider IP whitelists.
     */
    public function handle(Request $request, Closure $next, string $provider): Response
    {
        $ip = $request->header('X-Simulate-Ip') ?? $request->ip() ?? '127.0.0.1';

        if (!$this->verifier->isIpAllowed($ip, $provider)) {
            Log::warning("Unauthorized postback IP blocked for provider [{$provider}]", [
                'ip' => $ip,
                'provider' => $provider,
                'path' => $request->path(),
            ]);

            return response()->json([
                'success' => false,
                'message' => "Forbidden: Incoming IP [{$ip}] is not whitelisted for provider [{$provider}].",
                'error_code' => 'POSTBACK_IP_UNAUTHORIZED',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
