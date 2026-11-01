<?php

namespace App\Http\Middleware;

use App\Services\Fraud\AntiFraudService;
use App\Services\Fraud\DTOs\IpInspectionResult;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceAntiFraudGuard
{
    public function __construct(
        protected AntiFraudService $antiFraud
    ) {}

    /**
     * Handle an incoming request and enforce IP proxy/VPN/Tor/Datacenter boundaries.
     */
    public function handle(Request $request, Closure $next, ?string $action = 'request'): Response
    {
        $ip = $request->ip() ?? '127.0.0.1';

        // 1. Check for simulated testing flags
        if (app()->environment(['local', 'testing']) && $request->hasHeader('X-Simulate-Fraud')) {
            $simType = strtolower($request->header('X-Simulate-Fraud'));
            $inspection = new IpInspectionResult(
                ip: $ip,
                isVpn: $simType === 'vpn',
                isProxy: $simType === 'proxy',
                isTor: $simType === 'tor',
                isDatacenter: $simType === 'datacenter',
                riskScore: 99,
                source: 'simulation'
            );
        } else {
            $inspection = $this->antiFraud->inspectIp($ip);
        }

        // 2. Reject request if connection is anonymous or flagged
        if ($inspection->isFraudulent()) {
            // Log access attempt as rejected
            $this->antiFraud->logAccess($request, $request->user(), "blocked_{$action}", $inspection);

            $reason = 'VPN, proxy, or datacenter';
            if ($inspection->isTor) $reason = 'Tor exit node';
            elseif ($inspection->isVpn) $reason = 'VPN service';
            elseif ($inspection->isDatacenter) $reason = 'Hosting / Datacenter network';

            return response()->json([
                'success' => false,
                'message' => "Access denied: {$reason} connection detected. Please disable anonymous proxies to access rewards.",
                'error_code' => 'FRAUD_IP_BLOCKED',
                'data' => [
                    'ip' => $ip,
                    'risk_score' => $inspection->riskScore,
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        // 3. Log clean request
        $this->antiFraud->logAccess($request, $request->user(), $action, $inspection);

        // 4. Multi-accounting check for authenticated users
        if ($request->user()) {
            $this->antiFraud->checkForMultiAccounting($request, $request->user());
        }

        return $next($request);
    }
}
