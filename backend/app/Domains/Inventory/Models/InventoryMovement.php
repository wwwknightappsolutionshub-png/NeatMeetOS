<?php

namespace App\Domains\Inventory\Models;

use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\TeamMember;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'inventory_item_id',
        'location_id',
        'movement_type',
        'quantity_delta',
        'quantity_before',
        'quantity_after',
        'unit_cost_cents',
        'reference_type',
        'reference_id',
        'notes',
        'metadata',
        'performed_by_team_member_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'decimal:3',
            'quantity_before' => 'decimal:3',
            'quantity_after' => 'decimal:3',
            'unit_cost_cents' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'performed_by_team_member_id');
    }
}
