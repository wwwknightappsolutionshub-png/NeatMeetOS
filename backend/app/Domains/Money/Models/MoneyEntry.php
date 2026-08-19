<?php

namespace App\Domains\Money\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class MoneyEntry extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const KIND_CASH_IN = 'cash_in';

    public const KIND_SPEND = 'spend';

    public const CATEGORY_CASH = 'cash';

    public const CATEGORY_RENT = 'rent';

    public const CATEGORY_PRODUCTS = 'products';

    public const CATEGORY_TRAVEL = 'travel';

    public const CATEGORY_ADS = 'ads';

    public const CATEGORY_PHONE = 'phone';

    public const CATEGORY_OTHER = 'other';

    /**
     * @return list<string>
     */
    public static function kinds(): array
    {
        return [self::KIND_CASH_IN, self::KIND_SPEND];
    }

    /**
     * @return list<string>
     */
    public static function spendCategories(): array
    {
        return [
            self::CATEGORY_RENT,
            self::CATEGORY_PRODUCTS,
            self::CATEGORY_TRAVEL,
            self::CATEGORY_ADS,
            self::CATEGORY_PHONE,
            self::CATEGORY_OTHER,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function spendCategoryLabels(): array
    {
        return [
            self::CATEGORY_RENT => 'Rent / chair',
            self::CATEGORY_PRODUCTS => 'Products',
            self::CATEGORY_TRAVEL => 'Travel',
            self::CATEGORY_ADS => 'Ads',
            self::CATEGORY_PHONE => 'Phone',
            self::CATEGORY_OTHER => 'Other',
        ];
    }

    protected $fillable = [
        'tenant_id',
        'kind',
        'category',
        'amount_cents',
        'occurred_on',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'occurred_on' => 'date',
        ];
    }
}
