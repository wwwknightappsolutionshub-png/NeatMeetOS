<?php

namespace App\Domains\Crm\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class ClientReferralSetting extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const DEFAULT_SHARE_HEADING = 'Get Special Grooming Treats';

    public const DEFAULT_SHARE_BODY = "There's more to explore at {{business.name}}, book a visit via this link {{join_or_book_link}} and get free 300 Grooming Points when you join the membership plan / package. Save more always!";

    public const DEFAULT_THANK_YOU_SUBJECT = 'Thank you for your referral';

    public const DEFAULT_THANK_YOU_BODY = 'Thank you for inviting your friend, you have been rewarded accordingly. Keep up with the energy, we appreciate you.';

    protected $fillable = [
        'tenant_id',
        'enabled',
        'referrer_points',
        'referred_points',
        'share_heading',
        'share_body_template',
        'thank_you_subject',
        'thank_you_body_text',
        'max_email_invites_per_send',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'referrer_points' => 'integer',
            'referred_points' => 'integer',
            'max_email_invites_per_send' => 'integer',
        ];
    }
}
