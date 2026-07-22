<?php

namespace App\Domains\Crm\Models;

use App\Domains\Identity\Models\User;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientTimelineEvent extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const EVENT_CLIENT_CREATED = 'client.created';

    public const EVENT_CLIENT_UPDATED = 'client.updated';

    public const EVENT_CLIENT_ACTIVATED = 'client.activated';

    public const EVENT_CLIENT_DEACTIVATED = 'client.deactivated';

    public const EVENT_NOTE_ADDED = 'note.added';

    public const EVENT_CONSENT_UPDATED = 'consent.updated';

    public const EVENT_TAG_ASSIGNED = 'tag.assigned';

    public const EVENT_TAG_REMOVED = 'tag.removed';

    public const EVENT_COMMUNICATION = 'communication.recorded';

    public const EVENT_FORMULA_CREATED = 'formula.created';

    public const EVENT_FORMULA_UPDATED = 'formula.updated';

    public const EVENT_FORMULA_ARCHIVED = 'formula.archived';

    public const EVENT_PHOTO_ADDED = 'photo.added';

    public const EVENT_PHOTO_ARCHIVED = 'photo.archived';

    public const EVENT_DOCUMENT_ADDED = 'document.added';

    public const EVENT_DOCUMENT_ARCHIVED = 'document.archived';

    public const EVENT_PROFILE_PREFERENCES_UPDATED = 'profile.preferences_updated';

    public const EVENT_VISIT_CHECKIN = 'visit.checkin';

    protected $fillable = [
        'tenant_id',
        'client_id',
        'event_type',
        'title',
        'description',
        'payload',
        'actor_user_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
