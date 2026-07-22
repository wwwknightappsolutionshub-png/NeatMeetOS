<?php

namespace App\Domains\Integrations\Models;

use App\Domains\Identity\Models\TeamMember;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProviderAccount extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'provider_accounts';

    protected $fillable = [
        'tenant_id',
        'name',
        'category',
        'driver',
        'status',
        'is_default',
        'configuration_json',
        'credentials_json',
        'webhook_secret',
        'from_name',
        'from_address',
        'reply_to',
        'phone_number',
        'metadata_json',
        'last_tested_at',
        'last_test_result',
        'created_by_team_member_id',
        'updated_by_team_member_id',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'configuration_json' => 'array',
            'credentials_json' => 'encrypted:array',
            'metadata_json' => 'array',
            'is_default' => 'boolean',
            'last_tested_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'created_by_team_member_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'updated_by_team_member_id');
    }

    public function deliveryAttempts(): HasMany
    {
        return $this->hasMany(ProviderDeliveryAttempt::class, 'provider_account_id');
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(ProviderWebhookEvent::class, 'provider_account_id');
    }
}
