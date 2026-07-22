<?php

namespace App\Domains\Identity\Http\Controllers\Platform;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Models\PlatformSignupFormDefinition;
use App\Domains\Identity\Services\SignupFormDefinitionService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformSignupFormController extends Controller
{
    public function __construct(private readonly SignupFormDefinitionService $forms) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(
            $this->forms->list()->map(fn (PlatformSignupFormDefinition $f) => $this->serialize($f))->values()->all(),
        );
    }

    public function show(string $id): JsonResponse
    {
        $form = PlatformSignupFormDefinition::query()->findOrFail($id);

        return ApiResponse::success($this->serialize($form));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'steps' => ['required', 'array', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $form = $this->forms->create($data);

        return ApiResponse::success($this->serialize($form), 'Created', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $form = PlatformSignupFormDefinition::query()->findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'slug' => ['sometimes', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'steps' => ['sometimes', 'array', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $form = $this->forms->update($form, $data);

        return ApiResponse::success($this->serialize($form), 'Updated');
    }

    public function destroy(string $id): JsonResponse
    {
        $form = PlatformSignupFormDefinition::query()->findOrFail($id);
        $this->forms->delete($form);

        return ApiResponse::success(null, 'Deleted');
    }

    private function serialize(PlatformSignupFormDefinition $form): array
    {
        return [
            'id' => $form->id,
            'name' => $form->name,
            'slug' => $form->slug,
            'description' => $form->description,
            'steps' => $form->steps,
            'is_active' => $form->is_active,
            'version' => $form->version,
            'created_at' => $form->created_at?->toIso8601String(),
            'updated_at' => $form->updated_at?->toIso8601String(),
        ];
    }
}
