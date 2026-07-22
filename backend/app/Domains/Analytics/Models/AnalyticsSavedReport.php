<?php

namespace App\Domains\Analytics\Models;

use App\Domains\Identity\Models\TeamMember;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalyticsSavedReport extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'analytics_saved_reports';

    protected $fillable = [
        'tenant_id',
        'created_by_team_member_id',
        'name',
        'report_type',
        'filters_json',
        'export_format',
        'is_scheduled',
        'schedule_frequency',
        'schedule_day_of_week',
        'schedule_day_of_month',
        'schedule_time',
        'last_run_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'filters_json' => 'array',
            'is_scheduled' => 'boolean',
            'schedule_day_of_week' => 'integer',
            'schedule_day_of_month' => 'integer',
            'last_run_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'created_by_team_member_id');
    }

    public function exportJobs(): HasMany
    {
        return $this->hasMany(AnalyticsExportJob::class, 'analytics_saved_report_id');
    }
}
