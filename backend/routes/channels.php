<?php

use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Broadcast::channel('tenant.{tenantId}.booking-board', function (User $user, string $tenantId) {
    return TeamMember::withoutGlobalScopes()
        ->where('user_id', $user->id)
        ->where('tenant_id', $tenantId)
        ->where('is_active', true)
        ->exists();
});
