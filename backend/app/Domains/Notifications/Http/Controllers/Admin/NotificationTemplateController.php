<?php

namespace App\Domains\Notifications\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Notifications\Enums\NotificationCategory;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Http\Resources\NotificationTemplateResource;
use App\Domains\Notifications\Services\NotificationScopeValidator;
use App\Domains\Notifications\Services\NotificationStarterTemplateService;
use App\Domains\Notifications\Services\NotificationTemplateService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationTemplateController extends Controller
{
    public function __construct(
        private readonly NotificationTemplateService $templateService,
        private readonly NotificationStarterTemplateService $starterTemplates,
        private readonly NotificationScopeValidator $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'channel' => ['nullable', Rule::in(NotificationChannel::all())],
            'category' => ['nullable', Rule::in(NotificationCategory::all())],
            'is_active' => ['nullable', 'boolean'],
            'is_system' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return ApiResponse::success(NotificationTemplateResource::collection($this->templateService->list($filters)));
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(new NotificationTemplateResource($this->templateService->find($id)));
    }

    public function installSamples(): JsonResponse
    {
        $result = $this->starterTemplates->installSamples();

        return ApiResponse::success([
            'created' => $result['created'],
            'skipped' => $result['skipped'],
        ], $result['created'] > 0
            ? "Installed {$result['created']} sample template(s)"
            : 'Sample templates already installed');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'channel' => ['required', Rule::in(NotificationChannel::all())],
            'category' => ['required', Rule::in(NotificationCategory::all())],
            'subject' => ['nullable', 'string', 'max:255'],
            'body_text' => ['nullable', 'string', 'max:10000'],
            'body_html' => ['nullable', 'string', 'max:20000'],
            'variables_json' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $data['created_by_team_member_id'] = $teamMember?->id;

        $template = $this->templateService->create($data);

        return ApiResponse::success(new NotificationTemplateResource($template), 'Template created', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'channel' => ['sometimes', Rule::in(NotificationChannel::all())],
            'category' => ['sometimes', Rule::in(NotificationCategory::all())],
            'subject' => ['nullable', 'string', 'max:255'],
            'body_text' => ['nullable', 'string', 'max:10000'],
            'body_html' => ['nullable', 'string', 'max:20000'],
            'variables_json' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $template = $this->templateService->update($this->scope->findTemplate($id), $data);

        return ApiResponse::success(new NotificationTemplateResource($template), 'Template updated');
    }

    public function archive(string $id): JsonResponse
    {
        $template = $this->templateService->archive($this->scope->findTemplate($id));

        return ApiResponse::success(new NotificationTemplateResource($template), 'Template archived');
    }
}
