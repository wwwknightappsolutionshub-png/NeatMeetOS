<?php

namespace App\Domains\Integrations\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Integrations\Enums\ProviderAccountStatus;
use App\Domains\Integrations\Enums\ProviderCategory;
use App\Domains\Integrations\Enums\ProviderDriver;
use App\Domains\Integrations\Http\Resources\ProviderAccountResource;
use App\Domains\Integrations\Services\IntegrationsScopeValidator;
use App\Domains\Integrations\Services\ProviderAccountService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProviderAccountController extends Controller
{
    public function __construct(
        private readonly ProviderAccountService $accounts,
        private readonly IntegrationsScopeValidator $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'category' => ['nullable', Rule::in(ProviderCategory::all())],
            'status' => ['nullable', Rule::in(ProviderAccountStatus::all())],
            'archived' => ['nullable', 'boolean'],
        ]);

        return ApiResponse::success(
            ProviderAccountResource::collection($this->accounts->list($filters)),
        );
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(new ProviderAccountResource($this->accounts->find($id)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);
        $teamMember = $request->attributes->get('team_member');
        $account = $this->accounts->create($data, $teamMember?->id);

        return ApiResponse::success(new ProviderAccountResource($account), 'Provider account created', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $this->validatePayload($request, partial: true);
        $teamMember = $request->attributes->get('team_member');
        $account = $this->accounts->update($this->scope->findProviderAccount($id), $data, $teamMember?->id);

        return ApiResponse::success(new ProviderAccountResource($account), 'Provider account updated');
    }

    public function activate(Request $request, string $id): JsonResponse
    {
        $teamMember = $request->attributes->get('team_member');
        $account = $this->accounts->activate($this->scope->findProviderAccount($id), $teamMember?->id);

        return ApiResponse::success(new ProviderAccountResource($account), 'Provider account activated');
    }

    public function deactivate(Request $request, string $id): JsonResponse
    {
        $teamMember = $request->attributes->get('team_member');
        $account = $this->accounts->deactivate($this->scope->findProviderAccount($id), $teamMember?->id);

        return ApiResponse::success(new ProviderAccountResource($account), 'Provider account deactivated');
    }

    public function archive(Request $request, string $id): JsonResponse
    {
        $teamMember = $request->attributes->get('team_member');
        $account = $this->accounts->archive($this->scope->findProviderAccount($id), $teamMember?->id);

        return ApiResponse::success(new ProviderAccountResource($account), 'Provider account archived');
    }

    public function setDefault(Request $request, string $id): JsonResponse
    {
        $teamMember = $request->attributes->get('team_member');
        $account = $this->accounts->setDefault($this->scope->findProviderAccount($id), $teamMember?->id);

        return ApiResponse::success(new ProviderAccountResource($account), 'Default provider account set');
    }

    public function test(Request $request, string $id): JsonResponse
    {
        $teamMember = $request->attributes->get('team_member');
        $account = $this->accounts->testConnection($this->scope->findProviderAccount($id), $teamMember?->id);

        return ApiResponse::success(new ProviderAccountResource($account), 'Provider connection tested');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'category' => [$required, Rule::in(ProviderCategory::all())],
            'driver' => [$required, Rule::in(ProviderDriver::all())],
            'status' => ['nullable', Rule::in(ProviderAccountStatus::all())],
            'is_default' => ['nullable', 'boolean'],
            'configuration' => ['nullable', 'array'],
            'credentials' => ['nullable', 'array'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'string', 'max:255'],
            'reply_to' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'metadata' => ['nullable', 'array'],
        ]);
    }
}
