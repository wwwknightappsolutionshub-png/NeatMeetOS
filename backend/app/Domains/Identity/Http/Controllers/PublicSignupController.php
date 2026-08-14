<?php

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Services\AddressLookupService;
use App\Domains\Identity\Services\SignupFormDefinitionService;
use App\Domains\Identity\Services\TenantSignupService;
use App\Shared\Support\ApiResponse;
use App\Shared\Support\PublicStorageUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PublicSignupController extends Controller
{
    public function __construct(
        private readonly SignupFormDefinitionService $forms,
        private readonly TenantSignupService $signup,
        private readonly AddressLookupService $addresses,
    ) {}

    public function form(): JsonResponse
    {
        return ApiResponse::success($this->forms->activePublicPayload());
    }

    public function addressLookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'postcode' => ['required', 'string', 'min:5', 'max:12'],
        ]);

        return ApiResponse::success($this->addresses->lookup($data['postcode']));
    }

    public function uploadServiceImage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'max:4096'],
        ]);

        $path = $data['image']->store('signup/services', 'public');
        $url = PublicStorageUrl::fromDiskPath($path);

        return ApiResponse::success([
            'url' => $url,
            'path' => $path,
        ], 'Uploaded', 201);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'answers' => ['required', 'array'],
            'answers.business_name' => ['required', 'string', 'max:160'],
            'answers.trading_name' => ['nullable', 'string', 'max:160'],
            'answers.slug' => ['nullable', 'string', 'max:80'],
            'answers.business_type' => ['required', 'string', 'max:40'],
            'answers.timezone' => ['required', 'string', 'max:64'],
            'answers.owner_first_name' => ['required', 'string', 'max:80'],
            'answers.owner_last_name' => ['required', 'string', 'max:80'],
            'answers.owner_email' => ['required', 'email', 'max:160'],
            'answers.owner_whatsapp' => ['required', 'string', 'min:8', 'max:40'],
            'answers.contact_email' => ['required', 'email', 'max:160'],
            'answers.location_name' => ['required', 'string', 'max:120'],
            'answers.address_line1' => ['required', 'string', 'max:200'],
            'answers.city' => ['required', 'string', 'max:120'],
            'answers.postcode' => ['required', 'string', 'max:40'],
            'answers.country' => ['required', 'string', 'max:8'],
            'answers.opening_time' => ['required', 'string', 'max:12'],
            'answers.closing_time' => ['required', 'string', 'max:12'],
            'answers.desired_plan_slug' => ['required', 'string', 'in:basic,pro,diamond'],
            'answers.referral_code' => ['nullable', 'string', 'max:32'],
            'answers.services' => ['required', 'array', 'min:1', 'max:4'],
            'answers.services.*.name' => ['required', 'string', 'max:255'],
            'answers.services.*.category' => ['nullable', 'string', 'max:100'],
            'answers.services.*.description' => ['nullable', 'string', 'max:5000'],
            'answers.services.*.image_url' => ['nullable', 'string', 'max:2048'],
            'answers.services.*.duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'answers.services.*.base_price_cents' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $result = $this->signup->register($data['answers']);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('signup.register_failed', [
                'email' => $data['answers']['owner_email'] ?? null,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);
            throw $e;
        }

        return ApiResponse::success([
            'tenant' => [
                'id' => $result['tenant']->id,
                'name' => $result['tenant']->name,
                'slug' => $result['tenant']->slug,
                'status' => $result['tenant']->status,
            ],
            'activation_sent' => $result['activation_sent'],
            'message' => 'Check your email to activate your account and start your 30-day Basic trial.',
        ], 'Registered', 201);
    }

    public function lead(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'referral_code' => ['nullable', 'string', 'max:32'],
            'website' => ['nullable', 'string', 'max:200'],
            'hp_trap' => ['nullable', 'string', 'max:200'],
            'whatsapp' => ['nullable', 'string', 'min:8', 'max:40'],
        ]);

        $result = $this->signup->captureLead($data);

        return ApiResponse::success($result, $result['message'], $result['status'] === 'existing' ? 200 : 201);
    }

    public function completeWorkspace(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
            'answers' => ['required', 'array'],
            'answers.business_name' => ['required', 'string', 'max:160'],
            'answers.trading_name' => ['nullable', 'string', 'max:160'],
            'answers.slug' => ['nullable', 'string', 'max:80'],
            'answers.business_type' => ['required', 'string', 'max:40'],
            'answers.timezone' => ['required', 'string', 'max:64'],
            'answers.owner_first_name' => ['required', 'string', 'max:80'],
            'answers.owner_last_name' => ['required', 'string', 'max:80'],
            'answers.owner_email' => ['nullable', 'email', 'max:160'],
            'answers.owner_whatsapp' => ['required', 'string', 'min:8', 'max:40'],
            'answers.contact_email' => ['required', 'email', 'max:160'],
            'answers.location_name' => ['required', 'string', 'max:120'],
            'answers.address_line1' => ['required', 'string', 'max:200'],
            'answers.city' => ['required', 'string', 'max:120'],
            'answers.postcode' => ['required', 'string', 'max:40'],
            'answers.country' => ['required', 'string', 'max:8'],
            'answers.opening_time' => ['required', 'string', 'max:12'],
            'answers.closing_time' => ['required', 'string', 'max:12'],
            'answers.desired_plan_slug' => ['required', 'string', 'in:basic,pro,diamond'],
            'answers.referral_code' => ['nullable', 'string', 'max:32'],
            'answers.services' => ['required', 'array', 'min:1', 'max:4'],
            'answers.services.*.name' => ['required', 'string', 'max:255'],
            'answers.services.*.category' => ['nullable', 'string', 'max:100'],
            'answers.services.*.description' => ['nullable', 'string', 'max:5000'],
            'answers.services.*.image_url' => ['nullable', 'string', 'max:2048'],
            'answers.services.*.duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'answers.services.*.base_price_cents' => ['required', 'integer', 'min:0'],
        ]);

        /** @var \App\Domains\Identity\Models\User $user */
        $user = $request->user();

        try {
            $result = $this->signup->completeWorkspace(
                $user,
                $data['password'],
                $data['answers'],
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('signup.complete_workspace_failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);
            throw $e;
        }

        return ApiResponse::success([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'user' => [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
                'is_platform_admin' => (bool) $result['user']->is_platform_admin,
            ],
            'tenant' => [
                'id' => $result['tenant']->id,
                'name' => $result['tenant']->name,
                'slug' => $result['tenant']->slug,
            ],
            'workspace_incomplete' => false,
            'message' => 'Your workspace is ready. Welcome to your 30-day Basic trial.',
        ], 'Workspace created', 201);
    }

    public function activate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ]);

        $result = $this->signup->activate($data['token'], $data['password']);

        return ApiResponse::success([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'user' => [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
                'is_platform_admin' => (bool) $result['user']->is_platform_admin,
            ],
            'tenant' => [
                'id' => $result['tenant']->id,
                'name' => $result['tenant']->name,
                'slug' => $result['tenant']->slug,
            ],
        ], 'Account activated');
    }
}
