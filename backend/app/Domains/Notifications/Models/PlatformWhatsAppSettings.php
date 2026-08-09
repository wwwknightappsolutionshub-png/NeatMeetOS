<?php

namespace App\Domains\Notifications\Models;

use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class PlatformWhatsAppSettings extends Model
{
    use HasUuid;

    public const PROVIDER_GENIUS = 'genius';

    public const PROVIDER_META = 'meta';

    public const PROVIDER_TWILIO = 'twilio';

    protected $table = 'platform_whatsapp_settings';

    protected $fillable = [
        'enabled',
        'provider',
        'api_key',
        'session_id',
        'base_url',
        'meta_phone_number_id',
        'meta_access_token',
        'twilio_account_sid',
        'twilio_auth_token',
        'twilio_from',
        'signup_welcome_enabled',
        'signup_welcome_trial_body',
        'signup_welcome_activation_body',
        'signup_welcome_banner_path',
        'signup_welcome_banner_url',
        'signup_welcome_banner_mime',
        'signup_welcome_banner_data',
    ];

    protected $hidden = [
        'signup_welcome_banner_data',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'signup_welcome_enabled' => 'boolean',
            'api_key' => 'encrypted',
            'meta_access_token' => 'encrypted',
            'twilio_auth_token' => 'encrypted',
        ];
    }

    /**
     * @return list<string>
     */
    public static function providers(): array
    {
        return [
            self::PROVIDER_GENIUS,
            self::PROVIDER_META,
            self::PROVIDER_TWILIO,
        ];
    }
}
