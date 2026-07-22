<?php

namespace App\Shared\Middleware;

use App\Domains\Identity\Models\TeamMember;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LoadTeamMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $tenant = $request->attributes->get('tenant');

        $query = TeamMember::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->with(['roles.permissions']);

        if ($tenant !== null) {
            $query->where('tenant_id', $tenant->id);
        }

        $teamMember = $query->first();

        if ($teamMember !== null) {
            $request->attributes->set('team_member', $teamMember);
            $permissions = $teamMember->roles
                ->flatMap(fn ($role) => $role->permissions)
                ->pluck('id')
                ->unique()
                ->values()
                ->all();
            $request->attributes->set('permissions', $permissions);
        }

        return $next($request);
    }
}
