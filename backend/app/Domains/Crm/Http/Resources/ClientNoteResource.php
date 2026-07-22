<?php

namespace App\Domains\Crm\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domains\Crm\Models\ClientNote */
class ClientNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'note_type' => $this->note_type,
            'body' => $this->body,
            'author_team_member_id' => $this->author_team_member_id,
            'author_name' => $this->whenLoaded('author', fn () => $this->author?->display_name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
