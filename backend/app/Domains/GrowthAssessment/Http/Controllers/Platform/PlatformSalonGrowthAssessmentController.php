<?php

namespace App\Domains\GrowthAssessment\Http\Controllers\Platform;

use App\Domains\GrowthAssessment\Models\SalonGrowthAssessment;
use App\Domains\GrowthAssessment\Services\SalonGrowthAssessmentService;
use App\Domains\Identity\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformSalonGrowthAssessmentController extends Controller
{
    public function __construct(
        private readonly SalonGrowthAssessmentService $assessments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->assessments->listForPlatform(
            $request->only([
                'lead_status',
                'business_type',
                'uses_software',
                'postcode',
                'score_min',
                'score_max',
                'opportunity_min_cents',
                'from',
                'to',
                'q',
                'sort',
                'dir',
            ]),
            (int) $request->integer('per_page', 25),
        );

        $rows = collect($paginator->items())->map(fn (SalonGrowthAssessment $a) => [
            'id' => $a->id,
            'business_name' => $a->business_name,
            'business_type' => $a->business_type,
            'contact_name' => $a->contact_name,
            'email' => $a->email,
            'phone' => $a->phone,
            'postcode' => $a->postcode,
            'score_overall' => $a->score_overall,
            'estimated_opportunity_cents' => $a->estimated_opportunity_cents,
            'estimated_opportunity_display' => '£'.number_format($a->estimated_opportunity_cents / 100, 0),
            'primary_opportunity_label' => $a->primary_opportunity_label,
            'uses_software' => $a->uses_software,
            'software_satisfaction' => $a->software_satisfaction,
            'lead_status' => $a->lead_status,
            'email_delivery_status' => $a->email_delivery_status,
            'whatsapp_delivery_status' => $a->whatsapp_delivery_status,
            'assigned_platform_user' => $a->assignedPlatformUser,
            'created_at' => $a->created_at?->toIso8601String(),
            'next_follow_up_on' => $a->next_follow_up_on?->format('Y-m-d'),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $rows,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
                'lead_statuses' => SalonGrowthAssessment::LEAD_STATUSES,
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $assessment = SalonGrowthAssessment::query()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->assessments->platformDetail($assessment),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $assessment = SalonGrowthAssessment::query()->findOrFail($id);

        $data = $request->validate([
            'lead_status' => ['sometimes', 'string', Rule::in(SalonGrowthAssessment::LEAD_STATUSES)],
            'assigned_platform_user_id' => ['sometimes', 'nullable', 'uuid', 'exists:users,id'],
            'internal_notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'last_contacted_at' => ['sometimes', 'nullable', 'date'],
            'next_follow_up_on' => ['sometimes', 'nullable', 'date'],
        ]);

        $updated = $this->assessments->updateLead($assessment, $data);

        return response()->json([
            'success' => true,
            'message' => 'Lead updated.',
            'data' => $this->assessments->platformDetail($updated),
        ]);
    }
}
