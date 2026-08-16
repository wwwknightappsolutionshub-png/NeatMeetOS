<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\BookableService;
use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Workspace;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

class BookingScopeValidator
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function tenantId(): string
    {
        $id = $this->tenantContext->id();

        if ($id === null) {
            throw ValidationException::withMessages(['tenant' => ['Tenant context is required.']]);
        }

        return $id;
    }

    public function tenantTimezone(): string
    {
        $tz = $this->tenantContext->get()?->timezone;

        return is_string($tz) && $tz !== '' ? $tz : (string) config('app.timezone', 'UTC');
    }

    public function assertTenantModel(object $model): void
    {
        if (property_exists($model, 'tenant_id') && $model->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages(['resource' => ['Resource not found.']]);
        }
    }

    public function findClient(string $id): Client
    {
        $client = Client::query()->findOrFail($id);
        $this->assertTenantModel($client);

        return $client;
    }

    public function findTeamMember(string $id): TeamMember
    {
        $member = TeamMember::query()->findOrFail($id);
        $this->assertTenantModel($member);

        return $member;
    }

    public function findLocation(string $id): Location
    {
        $location = Location::query()->findOrFail($id);
        $this->assertTenantModel($location);

        return $location;
    }

    public function findWorkspace(?string $id): ?Workspace
    {
        if ($id === null) {
            return null;
        }

        $workspace = Workspace::query()->findOrFail($id);
        $this->assertTenantModel($workspace);

        return $workspace;
    }

    public function findBookableService(string $id): BookableService
    {
        $service = BookableService::query()->findOrFail($id);
        $this->assertTenantModel($service);

        return $service;
    }
}
