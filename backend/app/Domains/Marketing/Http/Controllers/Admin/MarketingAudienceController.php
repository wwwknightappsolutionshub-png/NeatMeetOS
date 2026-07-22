<?php

namespace App\Domains\Marketing\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Http\Resources\MarketingAudienceResource;
use App\Domains\Marketing\Services\MarketingAudienceService;
use App\Domains\Marketing\Services\MarketingScopeValidator;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketingAudienceController extends Controller
{
    public function __construct(
        private readonly MarketingAudienceService $audienceService,
        private readonly MarketingScopeValidator $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return ApiResponse::success(MarketingAudienceResource::collection($this->audienceService->list($filters)));
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(new MarketingAudienceResource($this->audienceService->find($id)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'rules' => ['required', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $audience = $this->audienceService->create($data);

        return ApiResponse::success(new MarketingAudienceResource($audience), 'Audience created', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'rules' => ['sometimes', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $audience = $this->audienceService->update($this->scope->findAudience($id), $data);

        return ApiResponse::success(new MarketingAudienceResource($audience), 'Audience updated');
    }

    public function archive(string $id): JsonResponse
    {
        $audience = $this->audienceService->archive($this->scope->findAudience($id));

        return ApiResponse::success(new MarketingAudienceResource($audience), 'Audience archived');
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rules' => ['required', 'array'],
            'channel' => ['nullable', Rule::in(MarketingChannel::all())],
            'location_id' => ['nullable', 'uuid'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $preview = $this->audienceService->previewRules(
            $data['rules'],
            $data['channel'] ?? MarketingChannel::EMAIL,
            $data['limit'] ?? 20,
        );

        return ApiResponse::success($preview);
    }
}
