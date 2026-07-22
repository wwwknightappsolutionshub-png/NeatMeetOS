<?php

namespace App\Domains\Analytics\Http\Resources;

use App\Domains\Analytics\Enums\AnalyticsExportJobStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domains\Analytics\Models\AnalyticsExportJob */
class AnalyticsExportJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $completed = $this->status === AnalyticsExportJobStatus::COMPLETED;

        return [
            'id' => $this->id,
            'report_type' => $this->report_type,
            'export_format' => $this->export_format,
            'status' => $this->status,
            'filters' => $this->filters_json ?? (object) [],
            'file_name' => $this->file_name,
            'row_count' => $this->row_count,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'failure_reason' => $this->failure_reason,
            'saved_report' => $this->whenLoaded('savedReport', fn () => $this->savedReport ? [
                'id' => $this->savedReport->id,
                'name' => $this->savedReport->name,
            ] : null),
            'download_url' => $completed ? "/api/v1/admin/analytics/exports/{$this->id}/download" : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
