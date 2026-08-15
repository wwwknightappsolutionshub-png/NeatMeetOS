<?php

namespace App\Domains\Identity\Http\Resources;

use App\Domains\Identity\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tenant */
class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'trading_name' => $this->trading_name,
            'slug' => $this->slug,
            'status' => $this->status,
            'business_type' => $this->business_type,
            'timezone' => $this->timezone,
            'currency' => $this->currency ?: 'GBP',
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
