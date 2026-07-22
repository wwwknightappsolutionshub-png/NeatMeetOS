<?php

namespace App\Domains\Marketing\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Http\Resources\MarketingTemplateResource;
use App\Domains\Marketing\Services\MarketingScopeValidator;
use App\Domains\Marketing\Services\MarketingStarterTemplateService;
use App\Domains\Marketing\Services\MarketingTemplateService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketingTemplateController extends Controller
{
    public function __construct(
        private readonly MarketingTemplateService $templateService,
        private readonly MarketingScopeValidator $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'channel' => ['nullable', Rule::in(MarketingChannel::all())],
            'category' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return ApiResponse::success(MarketingTemplateResource::collection($this->templateService->list($filters)));
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(new MarketingTemplateResource($this->templateService->find($id)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'channel' => ['required', Rule::in(MarketingChannel::all())],
            'subject' => ['nullable', 'string', 'max:255'],
            'body_text' => ['nullable', 'string', 'max:20000'],
            'body_html' => ['nullable', 'string', 'max:50000'],
            'variables' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $template = $this->templateService->create($data);

        return ApiResponse::success(new MarketingTemplateResource($template), 'Template created', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'channel' => ['sometimes', Rule::in(MarketingChannel::all())],
            'subject' => ['nullable', 'string', 'max:255'],
            'body_text' => ['nullable', 'string', 'max:20000'],
            'body_html' => ['nullable', 'string', 'max:50000'],
            'variables' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $template = $this->templateService->update($this->scope->findTemplate($id), $data);

        return ApiResponse::success(new MarketingTemplateResource($template), 'Template updated');
    }

    public function archive(string $id): JsonResponse
    {
        $template = $this->templateService->archive($this->scope->findTemplate($id));

        return ApiResponse::success(new MarketingTemplateResource($template), 'Template archived');
    }

    public function preview(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'variables' => ['nullable', 'array'],
            'client_id' => ['nullable', 'uuid'],
        ]);

        $preview = $this->templateService->preview($this->scope->findTemplate($id), $data);

        return ApiResponse::success($preview);
    }

    public function installSamples(MarketingStarterTemplateService $starters): JsonResponse
    {
        $result = $starters->installSamples();

        return ApiResponse::success([
            'created' => $result['created'],
            'skipped' => $result['skipped'],
        ], $result['created'] > 0
            ? "Installed {$result['created']} sample template(s)"
            : 'Sample templates already installed');
    }
}
