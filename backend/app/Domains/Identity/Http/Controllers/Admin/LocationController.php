<?php

namespace App\Domains\Identity\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Http\Resources\LocationResource;
use App\Domains\Identity\Services\LocationService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function __construct(private readonly LocationService $locationService) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(LocationResource::collection($this->locationService->list()));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:100'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geofence_radius_meters' => ['nullable', 'integer', 'min:10', 'max:5000'],
            'address' => ['nullable', 'array'],
            'address.line1' => ['nullable', 'string', 'max:255'],
            'address.line2' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:100'],
            'address.county' => ['nullable', 'string', 'max:100'],
            'address.postcode' => ['nullable', 'string', 'max:20'],
            'address.country' => ['nullable', 'string', 'max:2'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'opening_hours' => ['nullable', 'array'],
            'opening_hours.*.day_of_week' => ['required_with:opening_hours', 'integer', 'between:1,7'],
            'opening_hours.*.start_time' => ['nullable', 'string', 'max:8'],
            'opening_hours.*.end_time' => ['nullable', 'string', 'max:8'],
            'opening_hours.*.is_closed' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('opening_hours', $data)) {
            $data['opening_hours'] = $this->locationService->normalizeOpeningHours($data['opening_hours']);
        }

        $location = $this->locationService->create($data);

        return ApiResponse::success(new LocationResource($location), 'Location created', 201);
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(new LocationResource($this->locationService->find($id)));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:100'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geofence_radius_meters' => ['nullable', 'integer', 'min:10', 'max:5000'],
            'address' => ['nullable', 'array'],
            'address.line1' => ['nullable', 'string', 'max:255'],
            'address.line2' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:100'],
            'address.county' => ['nullable', 'string', 'max:100'],
            'address.postcode' => ['nullable', 'string', 'max:20'],
            'address.country' => ['nullable', 'string', 'max:2'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'opening_hours' => ['nullable', 'array'],
            'opening_hours.*.day_of_week' => ['required_with:opening_hours', 'integer', 'between:1,7'],
            'opening_hours.*.start_time' => ['nullable', 'string', 'max:8'],
            'opening_hours.*.end_time' => ['nullable', 'string', 'max:8'],
            'opening_hours.*.is_closed' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('opening_hours', $data)) {
            $data['opening_hours'] = $this->locationService->normalizeOpeningHours($data['opening_hours']);
        }

        $location = $this->locationService->update($this->locationService->find($id), $data);

        return ApiResponse::success(new LocationResource($location), 'Location updated');
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $location = $this->locationService->setActive(
            $this->locationService->find($id),
            $data['is_active'],
        );

        return ApiResponse::success(new LocationResource($location), 'Location status updated');
    }
}
