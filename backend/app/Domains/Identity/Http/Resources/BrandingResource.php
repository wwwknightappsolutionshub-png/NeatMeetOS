<?php

namespace App\Domains\Identity\Http\Resources;

use App\Shared\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'brand_display_name' => $this->resource['brand_display_name'] ?? null,
            'logo_url' => PublicStorageUrl::normalize(
                isset($this->resource['logo_url']) ? (string) $this->resource['logo_url'] : null,
            ),
            'primary_color' => $this->resource['primary_color'] ?? '#18181b',
            'secondary_color' => $this->resource['secondary_color'] ?? '#fafafa',
            'receipt_display_name' => $this->resource['receipt_display_name'] ?? null,
            'support_email' => $this->resource['support_email'] ?? null,
            'support_phone' => $this->resource['support_phone'] ?? null,
            'hero_emblem_mode' => $this->resource['hero_emblem_mode'] ?? 'none',
            'hero_emblem_url' => PublicStorageUrl::normalize(
                isset($this->resource['hero_emblem_url']) ? (string) $this->resource['hero_emblem_url'] : null,
            ),
            'hero_image_url' => PublicStorageUrl::normalize(
                isset($this->resource['hero_image_url']) ? (string) $this->resource['hero_image_url'] : null,
            ),
            'store_status' => $this->resource['store_status'] ?? 'auto',
            'social_facebook_url' => $this->resource['social_facebook_url'] ?? null,
            'social_instagram_url' => $this->resource['social_instagram_url'] ?? null,
            'social_tiktok_url' => $this->resource['social_tiktok_url'] ?? null,
        ];
    }
}
