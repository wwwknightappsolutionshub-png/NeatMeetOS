<?php

namespace App\Domains\AiHairstyle\Models;

use App\Domains\AiHairstyle\Support\AiHairstyleStatuses;
use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\User;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiHairstyleSession extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'public_token',
        'client_id',
        'status',
        'selected_preview_ids',
        'error_message',
        'provider',
        'external_job_id',
        'submitted_at',
        'accepted_at',
        'accepted_by_user_id',
        'expires_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'selected_preview_ids' => 'array',
            'metadata' => 'array',
            'submitted_at' => 'datetime',
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    public function previews(): HasMany
    {
        return $this->hasMany(AiHairstylePreview::class, 'session_id')
            ->orderBy('sort_order')
            ->orderBy('created_at');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            AiHairstyleStatuses::SESSION_ACCEPTED,
            AiHairstyleStatuses::SESSION_CANCELLED,
            AiHairstyleStatuses::SESSION_EXPIRED,
        ], true);
    }
}
