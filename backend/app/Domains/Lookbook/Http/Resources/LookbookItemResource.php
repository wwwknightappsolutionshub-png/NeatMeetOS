<?php

namespace App\Domains\Lookbook\Http\Resources;

use App\Shared\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LookbookItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'image_url' => PublicStorageUrl::normalize($this->image_url),
            'title' => $this->title,
            'caption' => $this->caption,
            'category_key' => $this->category_key,
            'sort_order' => $this->sort_order,
            'is_published' => $this->is_published,
            'is_seeded' => $this->is_seeded,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
