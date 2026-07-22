<?php

namespace App\Domains\Marketing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketingTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'channel' => $this->channel,
            'subject' => $this->subject,
            'body_text' => $this->body_text,
            'body_html' => $this->body_html,
            'variables' => $this->variables_json ?? [],
            'is_system' => $this->is_system,
            'is_active' => $this->is_active,
            'campaigns_count' => $this->whenCounted('campaigns'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
