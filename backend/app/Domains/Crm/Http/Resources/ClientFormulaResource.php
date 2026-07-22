<?php

namespace App\Domains\Crm\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domains\Crm\Models\ClientFormula */
class ClientFormulaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'title' => $this->title,
            'formula_body' => $this->formula_body,
            'category' => $this->category,
            'service_context' => $this->service_context,
            'recorded_by_team_member_id' => $this->recorded_by_team_member_id,
            'recorded_by_name' => $this->whenLoaded('recordedBy', fn () => $this->recordedBy?->display_name),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
