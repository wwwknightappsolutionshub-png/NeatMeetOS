<?php

namespace App\Domains\Analytics\Models;

use App\Domains\Analytics\Enums\AnalyticsExportJobStatus;
use App\Domains\Identity\Models\TeamMember;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsExportJob extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'analytics_export_jobs';

    protected $fillable = [
        'tenant_id',
        'analytics_saved_report_id',
        'created_by_team_member_id',
        'report_type',
        'export_format',
        'status',
        'filters_json',
        'file_name',
        'file_disk',
        'file_path',
        'row_count',
        'started_at',
        'completed_at',
        'failed_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'filters_json' => 'array',
            'row_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function isCompleted(): bool
    {
        return $this->status === AnalyticsExportJobStatus::COMPLETED;
    }

    public function savedReport(): BelongsTo
    {
        return $this->belongsTo(AnalyticsSavedReport::class, 'analytics_saved_report_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'created_by_team_member_id');
    }
}
