<?php

namespace App\Domains\Crm\Http\Controllers\PublicMember;

use App\Domains\Crm\Http\Resources\ClientLookResource;
use App\Domains\Crm\Services\ClientLookService;
use App\Domains\Crm\Services\MemberPortalExperienceService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MemberLooksController extends Controller
{
    public function __construct(
        private readonly MemberPortalExperienceService $experience,
        private readonly ClientLookService $looks,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $client = $this->experience->requireClient($this->bearerToken($request));

        return ApiResponse::success(
            ClientLookResource::collection($this->looks->listForClient($client))->resolve(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $client = $this->experience->requireClient($this->bearerToken($request));
        $data = $request->validate([
            'image' => ['required', 'image', 'max:8192'],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $look = $this->looks->upload($client, $data['image'], $data['caption'] ?? null);
        } catch (ValidationException $e) {
            return ApiResponse::error(
                collect($e->errors())->flatten()->first() ?: 'Could not save look',
                422,
                $e->errors(),
            );
        }

        return ApiResponse::success(
            (new ClientLookResource($look))->resolve(),
            'Look saved',
            201,
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $client = $this->experience->requireClient($this->bearerToken($request));

        try {
            $this->looks->delete($client, $id);
        } catch (ValidationException $e) {
            return ApiResponse::error(
                collect($e->errors())->flatten()->first() ?: 'Could not delete look',
                422,
                $e->errors(),
            );
        }

        return ApiResponse::success(null, 'Look deleted');
    }

    private function bearerToken(Request $request): string
    {
        $header = (string) $request->header('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return trim($m[1]);
        }

        return (string) $request->input('token', '');
    }
}
