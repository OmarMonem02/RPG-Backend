<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyPublicTicketRequest;
use App\Http\Resources\TicketPublicMetaResource;
use App\Http\Resources\TicketPublicResource;
use App\Services\TicketTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicTicketTrackingController extends Controller
{
    public function __construct(
        private readonly TicketTrackingService $trackingService,
    ) {
    }

    public function meta(string $token): JsonResponse
    {
        $ticket = $this->trackingService->findByPublicToken($token);

        if (! $ticket) {
            return $this->notFoundResponse();
        }

        return response()->json(new TicketPublicMetaResource($ticket));
    }

    public function verify(string $token, VerifyPublicTicketRequest $request): JsonResponse
    {
        $ticket = $this->trackingService->findByPublicToken($token);

        if (! $ticket || ! $this->trackingService->verifyPhone($ticket, $request->validated('phone'))) {
            return $this->invalidResponse();
        }

        $sessionId = $this->trackingService->createSession($ticket);

        return response()->json([
            'tracking_session' => $sessionId,
            'ticket' => new TicketPublicResource($ticket),
        ]);
    }

    public function show(string $token, Request $request): JsonResponse
    {
        $sessionId = $request->header(TicketTrackingService::SESSION_HEADER);

        if (! is_string($sessionId) || $sessionId === '') {
            return response()->json(['message' => 'Verification required.'], 401);
        }

        $ticket = $this->trackingService->resolveSession($sessionId, $token);

        if (! $ticket) {
            return $this->invalidResponse();
        }

        return response()->json([
            'ticket' => new TicketPublicResource($ticket),
        ]);
    }

    private function notFoundResponse(): JsonResponse
    {
        return response()->json(['message' => 'This tracking link is invalid or has expired.'], 404);
    }

    private function invalidResponse(): JsonResponse
    {
        return response()->json(['message' => 'Invalid link or phone.'], 422);
    }
}
