<?php

namespace App\Domains\GrowthAssessment\Http\Controllers\Public;

use App\Domains\GrowthAssessment\Http\Requests\SubmitSalonGrowthAssessmentRequest;
use App\Domains\GrowthAssessment\Services\SalonGrowthAssessmentService;
use App\Domains\Identity\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicSalonGrowthAssessmentController extends Controller
{
    public function __construct(
        private readonly SalonGrowthAssessmentService $assessments,
    ) {}

    public function store(SubmitSalonGrowthAssessmentRequest $request): JsonResponse
    {
        $payload = $this->assessments->submit(
            $request->validated(),
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Assessment completed.',
            'data' => $payload['result'],
        ], 201);
    }

    public function show(string $token): JsonResponse
    {
        $assessment = $this->assessments->findByPublicToken($token);
        if ($assessment === null) {
            return response()->json([
                'success' => false,
                'message' => 'Assessment not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->assessments->publicResultPayload($assessment),
        ]);
    }

    public function sendWhatsApp(Request $request, string $token): JsonResponse
    {
        $assessment = $this->assessments->findByPublicToken($token);
        if ($assessment === null) {
            return response()->json([
                'success' => false,
                'message' => 'Assessment not found.',
            ], 404);
        }

        $updated = $this->assessments->requestWhatsApp($assessment);

        return response()->json([
            'success' => true,
            'message' => $updated->whatsapp_delivery_status === 'sent'
                ? 'Assessment sent to WhatsApp.'
                : 'WhatsApp delivery attempted.',
            'data' => [
                'whatsapp_delivery_status' => $updated->whatsapp_delivery_status,
                'whatsapp_delivery_error' => $updated->whatsapp_delivery_error,
            ],
        ]);
    }
}
