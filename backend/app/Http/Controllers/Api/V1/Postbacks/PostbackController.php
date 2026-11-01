<?php

namespace App\Http\Controllers\Api\V1\Postbacks;

use App\Http\Controllers\Controller;
use App\Services\Postbacks\PostbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PostbackController extends Controller
{
    public function __construct(
        protected PostbackService $postbackService
    ) {}

    /**
     * BitLabs S2S Postback
     */
    public function handleBitLabs(Request $request): JsonResponse|Response
    {
        $result = $this->postbackService->handle('bitlabs', $request);

        return response()->json($result['response_data'], $result['status_code']);
    }

    /**
     * CPX Research S2S Postback
     */
    public function handleCpx(Request $request): JsonResponse|Response
    {
        $result = $this->postbackService->handle('cpx', $request);

        if (is_numeric($result['response_data']) || is_string($result['response_data'])) {
            return response((string) $result['response_data'], $result['status_code'])
                ->header('Content-Type', 'text/plain');
        }

        return response()->json($result['response_data'], $result['status_code']);
    }

    /**
     * Offerwall.gg S2S Postback
     */
    public function handleOfferwallGg(Request $request): JsonResponse|Response
    {
        $result = $this->postbackService->handle('offerwall_gg', $request);

        return response()->json($result['response_data'], $result['status_code']);
    }

    /**
     * Offerwall.com S2S Postback
     */
    public function handleOfferwallCom(Request $request): JsonResponse|Response
    {
        $result = $this->postbackService->handle('offerwall_com', $request);

        return response()->json($result['response_data'], $result['status_code']);
    }
}
