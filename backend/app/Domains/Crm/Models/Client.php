<?php

namespace App\Domains\Crm\Models;

use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\TeamMember;
use App\Shared\Support\PhoneNormalizer;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'first_name',
        'last_name',
        'display_name',
        'email',
        'phone',
        'phone_normalized',
        'date_of_birth',
        'special_event_month',
        'special_event_day',
        'special_event_label',
        'primary_location_id',
        'preferred_team_member_id',
        'internal_flags',
        'preferences',
        'loyalty_display_status',
        'last_visited_at',
        'membership_joined_at',
        'interested_next_visit_date',
        'is_active',
        'referred_by_client_id',
        'referral_invite_id',
        'referral_attributed_at',
        'referral_referred_bonus_awarded_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'special_event_month' => 'integer',
            'special_event_day' => 'integer',
            'last_visited_at' => 'datetime',
            'membership_joined_at' => 'datetime',
            'interested_next_visit_date' => 'date',
            'referral_attributed_at' => 'datetime',
            'referral_referred_bonus_awarded_at' => 'datetime',
            'internal_flags' => 'array',
            'preferences' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by_client_id');
    }

    public function referralInvite(): BelongsTo
    {
        return $this->belongsTo(ClientReferralInvite::class, 'referral_invite_id');
    }

    public function primaryLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'primary_location_id');
    }

    public function preferredTeamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'preferred_team_member_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ClientTag::class, 'client_client_tag', 'client_id', 'client_tag_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ClientNote::class);
    }

    public function consentRecords(): HasMany
    {
        return $this->hasMany(ClientConsentRecord::class);
    }

    public function timelineEvents(): HasMany
    {
        return $this->hasMany(ClientTimelineEvent::class);
    }

    public function formulas(): HasMany
    {
        return $this->hasMany(ClientFormula::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ClientPhoto::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ClientDocument::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(\App\Domains\Memberships\Models\ClientMembership::class);
    }

    public function walletEntries(): HasMany
    {
        return $this->hasMany(\App\Domains\Memberships\Models\ClientWalletEntry::class);
    }

    public function loyaltyEntries(): HasMany
    {
        return $this->hasMany(\App\Domains\Memberships\Models\ClientLoyaltyEntry::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(\App\Domains\Memberships\Models\ClientPackage::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(ClientVisit::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Client $client): void {
            if ($client->first_name !== null && trim((string) $client->first_name) === '') {
                $client->first_name = null;
            }
            if ($client->last_name !== null && trim((string) $client->last_name) === '') {
                $client->last_name = null;
            }

            $normalized = PhoneNormalizer::normalize($client->phone);
            $client->phone_normalized = $normalized !== '' ? $normalized : null;
        });
    }

    public function resolvedDisplayName(): string
    {
        if ($this->display_name) {
            return $this->display_name;
        }

        $name = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
        if ($name !== '') {
            return $name;
        }

        if (filled($this->phone)) {
            return (string) $this->phone;
        }

        if (filled($this->email)) {
            return (string) $this->email;
        }

        return 'Client';
    }
}
