<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\BookableService;
use App\Shared\Audit\AuditLogger;
use App\Shared\Support\PublicStorageUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class BookableServiceCatalogService
{
    public function __construct(
        private readonly BookingScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function list(bool $activeOnly = true): \Illuminate\Database\Eloquent\Collection
    {
        $query = BookableService::query()->orderBy('display_order')->orderBy('name');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    public function find(string $id): BookableService
    {
        $service = BookableService::query()->findOrFail($id);
        $this->scope->assertTenantModel($service);

        return $service;
    }

    public function create(array $data): BookableService
    {
        if (empty($data['image_url'])) {
            throw ValidationException::withMessages([
                'image_url' => ['A service image is required.'],
            ]);
        }

        $service = BookableService::query()->create([
            'tenant_id' => $this->scope->tenantId(),
            'name' => $data['name'],
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'image_url' => $data['image_url'],
            'duration_minutes' => $data['duration_minutes'],
            'base_price_cents' => $data['base_price_cents'] ?? null,
            'membership_price_cents' => $data['membership_price_cents'] ?? null,
            'loyalty_price_cents' => $data['loyalty_price_cents'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_bookable_online' => $data['is_bookable_online'] ?? false,
            'display_order' => $data['display_order'] ?? 0,
            'deposit_required' => $data['deposit_required'] ?? false,
            'deposit_amount_cents' => $data['deposit_amount_cents'] ?? null,
            'min_lead_time_hours' => $data['min_lead_time_hours'] ?? null,
            'cancellation_window_hours' => $data['cancellation_window_hours'] ?? null,
        ]);

        $this->auditLogger->log('booking_service.created', $service, null, $service->toArray());

        return $service;
    }

    public function update(BookableService $service, array $data): BookableService
    {
        $this->scope->assertTenantModel($service);

        $old = $service->toArray();
        $service->fill(collect($data)->only([
            'name',
            'category',
            'description',
            'image_url',
            'duration_minutes',
            'base_price_cents',
            'membership_price_cents',
            'loyalty_price_cents',
            'is_active',
            'is_bookable_online',
            'display_order',
            'deposit_required',
            'deposit_amount_cents',
            'min_lead_time_hours',
            'cancellation_window_hours',
        ])->all());
        $service->save();

        $this->auditLogger->log('booking_service.updated', $service, $old, $service->toArray());

        return $service->fresh();
    }

    /**
     * @return array{url: string, path: string}
     */
    public function uploadImage(UploadedFile $image): array
    {
        $tenantId = $this->scope->tenantId();
        $path = $image->store('services/'.$tenantId, 'public');
        $url = PublicStorageUrl::fromDiskPath($path);

        return [
            'url' => $url,
            'path' => $path,
        ];
    }

    public function archive(BookableService $service): BookableService
    {
        $this->scope->assertTenantModel($service);

        $service->is_active = false;
        $service->save();

        $this->auditLogger->log('booking_service.archived', $service);

        return $service->fresh();
    }

    /**
     * @param  array<int, array{booking_service_id: string, sort_order?: int, price_cents?: int|null, pricing_tier?: string|null}>  $lines
     * @return array{lines: array<int, array<string, mixed>>, total_minutes: int}
     */
    public function resolveServiceLines(array $lines): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages([
                'services' => ['At least one service is required.'],
            ]);
        }

        $resolved = [];
        $totalMinutes = 0;
        $sort = 0;

        foreach ($lines as $line) {
            $service = $this->find($line['booking_service_id']);

            if (! $service->is_active) {
                throw ValidationException::withMessages([
                    'services' => ["Service {$service->name} is not active."],
                ]);
            }

            $order = $line['sort_order'] ?? $sort;
            $resolved[] = [
                'booking_service_id' => $service->id,
                'service_name' => $service->name,
                'duration_minutes' => $service->duration_minutes,
                'price_cents' => array_key_exists('price_cents', $line) ? $line['price_cents'] : $service->base_price_cents,
                'pricing_tier' => $line['pricing_tier'] ?? 'regular',
                'sort_order' => $order,
                'package_entitlement_id' => $line['package_entitlement_id'] ?? null,
                'entitlement_source' => $line['entitlement_source'] ?? null,
            ];
            $totalMinutes += $service->duration_minutes;
            $sort++;
        }

        return ['lines' => $resolved, 'total_minutes' => $totalMinutes];
    }
}
