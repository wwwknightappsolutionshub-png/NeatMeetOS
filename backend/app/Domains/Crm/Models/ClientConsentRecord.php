<?php

namespace App\Domains\Crm\Models;

use App\Domains\Identity\Models\User;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientConsentRecord extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const TYPE_MARKETING_EMAIL = 'marketing_email';

    public const TYPE_MARKETING_SMS = 'marketing_sms';

    public const TYPE_PRIVACY_CONTACT = 'privacy_contact';

    public const SOURCE_IN_PERSON = 'in_person';

    public const SOURCE_ONLINE_FORM = 'online_form';

    public const SOURCE_STAFF_ENTRY = 'staff_entry';

    public const SOURCE_IMPORT = 'import';

    public static function types(): array
    {
        return [
            self::TYPE_MARKETING_EMAIL,
            self::TYPE_MARKETING_SMS,
            self::TYPE_PRIVACY_CONTACT,
        ];
    }

    public static function sources(): array
    {
        return [
            self::SOURCE_IN_PERSON,
            self::SOURCE_ONLINE_FORM,
            self::SOURCE_STAFF_ENTRY,
            self::SOURCE_IMPORT,
        ];
    }

    protected $fillable = [
        'tenant_id',
        'client_id',
        'consent_type',
        'granted',
        'source',
        'actor_user_id',
        'metadata',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
            'metadata' => 'array',
            'recorded_at' => 'datetime',
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
