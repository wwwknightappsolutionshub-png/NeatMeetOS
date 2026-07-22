<?php

namespace App\Domains\Pos\Services;

use App\Domains\Pos\Enums\CheckoutLineReturnStatus;
use App\Domains\Pos\Enums\DiscountType;
use App\Shared\Commerce\Models\CommerceCheckoutLine;

class CheckoutDiscountService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function mergeDiscountMetadata(CommerceCheckoutLine $line, array $data, ?string $teamMemberId): array
    {
        $attrs = [];

        if (array_key_exists('discount_cents', $data)) {
            $attrs['discount_cents'] = (int) $data['discount_cents'];
        }

        if (! empty($data['discount_type'])) {
            $attrs['discount_type'] = $data['discount_type'];
        } elseif (($attrs['discount_cents'] ?? $line->discount_cents ?? 0) > 0 && $line->discount_type === null) {
            $attrs['discount_type'] = DiscountType::MANUAL_AMOUNT;
        }

        if (array_key_exists('discount_reason', $data)) {
            $attrs['discount_reason'] = $data['discount_reason'];
        }

        if (! empty($data['discount_authorised_by_team_member_id'])) {
            $attrs['discount_authorised_by_team_member_id'] = $data['discount_authorised_by_team_member_id'];
        } elseif (! empty($data['discount_type']) && $data['discount_type'] === DiscountType::MANAGER_OVERRIDE) {
            $attrs['discount_authorised_by_team_member_id'] = $teamMemberId;
        }

        return $attrs;
    }

    public function defaultReturnStatus(): string
    {
        return CheckoutLineReturnStatus::NOT_RETURNED;
    }
}
