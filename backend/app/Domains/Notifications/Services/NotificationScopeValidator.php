<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Booking\Services\BookingScopeValidator;
use App\Domains\Crm\Models\Client;
use App\Domains\Notifications\Models\NotificationMessage;
use App\Domains\Notifications\Models\NotificationTemplate;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Tenant scoping + resource resolution for the Notifications domain.
 *
 * Mirrors the pattern used by MarketingScopeValidator: it wraps the shared
 * BookingScopeValidator for cross-domain entities (clients) and adds finders
 * for notification-owned aggregates.
 */
class NotificationScopeValidator
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BookingScopeValidator $bookingScope,
    ) {}

    public function tenantId(): string
    {
        return $this->bookingScope->tenantId();
    }

    public function assertTenantModel(object $model): void
    {
        if (isset($model->tenant_id) && $model->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages(['resource' => ['Resource not found.']]);
        }
    }

    public function findClient(string $id): Client
    {
        return $this->bookingScope->findClient($id);
    }

    public function findTemplate(string $id): NotificationTemplate
    {
        $template = NotificationTemplate::query()->findOrFail($id);
        $this->assertTenantModel($template);

        return $template;
    }

    public function findMessage(string $id): NotificationMessage
    {
        $message = NotificationMessage::query()->findOrFail($id);
        $this->assertTenantModel($message);

        return $message;
    }
}
