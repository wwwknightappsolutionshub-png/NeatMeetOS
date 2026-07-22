<?php

namespace App\Domains\Notifications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'channel' => $this->channel,
            'category' => $this->category,
            'subject' => $this->subject,
            'body_text' => $this->body_text,
            'body_html' => $this->body_html,
            'variables' => $this->variables_json ?? [],
            'is_system' => $this->is_system,
            'is_active' => $this->is_active,
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy?->id,
                'display_name' => $this->createdBy?->display_name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
