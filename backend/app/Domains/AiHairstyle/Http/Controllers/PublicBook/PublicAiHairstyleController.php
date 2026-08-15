<?php

namespace App\Domains\AiHairstyle\Http\Controllers\PublicBook;

use App\Domains\AiHairstyle\Services\PublicAiHairstyleFlowService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Services\TenantEntitlementService;
use App\Shared\Support\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PublicAiHairstyleController extends Controller
{
    public function __construct(
        private readonly PublicAiHairstyleFlowService $flow,
        private readonly TenantEntitlementService $entitlements,
        private readonly TenantContext $tenantContext,
    ) {}

    public function store(): JsonResponse
    {
        if ($deny = $this->featureDenied()) {
            return $deny;
        }

        $session = $this->flow->createSession();

        return ApiResponse::success($this->flow->toPublicArray($session), 'Look session created', 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if ($deny = $this->featureDenied()) {
            return $deny;
        }

        $validated = $request->validate([
            'public_token' => ['required', 'string', 'max:64'],
            'lite' => ['sometimes', 'boolean'],
        ]);

        try {
            $session = $this->flow->show($id, $validated['public_token'], withPreviews: false);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        }

        $lite = $request->boolean('lite') || $session->status === 'generating';
        if (! $lite) {
            $session->load('previews');
        }

        return ApiResponse::success($this->flow->toPublicArray($session, $lite));
    }

    public function generate(Request $request, string $id): JsonResponse
    {
        if ($deny = $this->featureDenied()) {
            return $deny;
        }

        $validated = $request->validate([
            'public_token' => ['required', 'string', 'max:64'],
            'selfie' => ['required', 'file', 'image', 'max:5120'],
        ]);

        try {
            $session = $this->flow->generate($id, $validated['public_token'], $request->file('selfie'));
        } catch (ValidationException $e) {
            return $this->validationError($e);
        }

        return ApiResponse::success($this->flow->toPublicArray($session), 'Generation started');
    }

    public function select(Request $request, string $id): JsonResponse
    {
        if ($deny = $this->featureDenied()) {
            return $deny;
        }

        $validated = $request->validate([
            'public_token' => ['required', 'string', 'max:64'],
            'preview_ids' => ['required', 'array', 'min:1'],
            'preview_ids.*' => ['uuid'],
        ]);

        try {
            $session = $this->flow->select($id, $validated['public_token'], $validated['preview_ids']);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        }

        return ApiResponse::success($this->flow->toPublicArray($session), 'Look selected');
    }

    public function submit(Request $request, string $id): JsonResponse
    {
        if ($deny = $this->featureDenied()) {
            return $deny;
        }

        $validated = $request->validate([
            'public_token' => ['required', 'string', 'max:64'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'min:7', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $session = $this->flow->submit($id, $validated['public_token'], [
                'first_name' => $validated['first_name'] ?? null,
                'last_name' => $validated['last_name'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'],
                'notes' => $validated['notes'] ?? null,
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        }

        return ApiResponse::success($this->flow->toPublicArray($session), 'Look submitted to the salon');
    }

    private function featureDenied(): ?JsonResponse
    {
        $tenant = $this->tenantContext->get();
        if ($this->entitlements->isEnabled($tenant, 'ai_hairstyle')) {
            return null;
        }

        return ApiResponse::error(
            'AI Hairstyle Preview is not available for this salon.',
            403,
            null,
            'feature_disabled',
        );
    }

    private function validationError(ValidationException $e): JsonResponse
    {
        return ApiResponse::error(
            collect($e->errors())->flatten()->first() ?: 'Validation failed',
            422,
            $e->errors(),
        );
    }
}
