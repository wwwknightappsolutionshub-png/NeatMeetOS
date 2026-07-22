<?php

namespace App\Domains\Notifications\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationMessageStatus;
use App\Domains\Notifications\Enums\NotificationPurpose;
use App\Domains\Notifications\Enums\NotificationSourceType;
use App\Domains\Notifications\Http\Resources\NotificationMessageResource;
use App\Domains\Notifications\Services\NotificationMessageService;
use App\Domains\Notifications\Services\NotificationScopeValidator;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationMessageController extends Controller
{
    public function __construct(
        private readonly NotificationMessageService $messageService,
        private readonly NotificationScopeValidator $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'channel' => ['nullable', Rule::in(NotificationChannel::all())],
            'status' => ['nullable', Rule::in(NotificationMessageStatus::all())],
            'source_type' => ['nullable', Rule::in(NotificationSourceType::all())],
            'purpose' => ['nullable', Rule::in(NotificationPurpose::all())],
            'client_id' => ['nullable', 'uuid'],
            'appointment_id' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'desk_only' => ['nullable', 'boolean'],
        ]);

        return ApiResponse::success(NotificationMessageResource::collection($this->messageService->list($filters)));
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(new NotificationMessageResource($this->messageService->find($id)));
    }

    public function storeManual(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'uuid'],
            'channel' => ['required', Rule::in(NotificationChannel::all())],
            'purpose' => ['nullable', Rule::in(NotificationPurpose::all())],
            'subject' => ['nullable', 'string', 'max:255'],
            'body_text' => ['nullable', 'string', 'max:10000'],
            'body_html' => ['nullable', 'string', 'max:20000'],
            'notification_template_id' => ['nullable', 'uuid'],
            'recipient_address' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $data['created_by_team_member_id'] = $teamMember?->id;

        $message = $this->messageService->createManual($data);

        return ApiResponse::success(new NotificationMessageResource($message), 'Message created', 201);
    }

    public function storeDesk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'body_text' => ['required', 'string', 'max:5000'],
            'subject' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $data['created_by_team_member_id'] = $teamMember?->id;

        $message = $this->messageService->createDeskNote($data);

        return ApiResponse::success(new NotificationMessageResource($message), 'Desk note posted', 201);
    }

    public function cancel(string $id): JsonResponse
    {
        $message = $this->messageService->cancel($this->scope->findMessage($id));

        return ApiResponse::success(new NotificationMessageResource($message), 'Message cancelled');
    }

    public function markDelivered(string $id): JsonResponse
    {
        $message = $this->messageService->markDelivered($this->scope->findMessage($id));

        return ApiResponse::success(new NotificationMessageResource($message), 'Message marked delivered');
    }
}
