<?php

namespace App\Domains\Analytics\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domains\Analytics\Models\AnalyticsSavedReport */
class AnalyticsSavedReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'report_type' => $this->report_type,
            'filters' => $this->filters_json ?? (object) [],
            'export_format' => $this->export_format,
            'is_scheduled' => (bool) $this->is_scheduled,
            'schedule_frequency' => $this->schedule_frequency,
            'schedule_day_of_week' => $this->schedule_day_of_week,
            'schedule_day_of_month' => $this->schedule_day_of_month,
            'schedule_time' => $this->schedule_time,
            'delivery_emails' => $this->delivery_emails ?? [],
            'last_run_at' => $this->last_run_at?->toIso8601String(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy?->id,
                'name' => $this->createdBy?->display_name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
