<?php

use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Existing tenants (e.g. Demo Salon) were created before gallery/lookbook/next_visit
 * permissions existed. Nav can unlock via progressive features while API RBAC still
 * returns Forbidden until Owner roles receive these permissions.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        ['id' => 'gallery.view', 'name' => 'View works gallery', 'slug' => 'gallery.view', 'module' => 'gallery'],
        ['id' => 'gallery.manage', 'name' => 'Manage works gallery', 'slug' => 'gallery.manage', 'module' => 'gallery'],
        ['id' => 'lookbook.view', 'name' => 'View lookbook', 'slug' => 'lookbook.view', 'module' => 'lookbook'],
        ['id' => 'lookbook.manage', 'name' => 'Manage lookbook', 'slug' => 'lookbook.manage', 'module' => 'lookbook'],
        ['id' => 'next_visit.view', 'name' => 'View next visit plans', 'slug' => 'next_visit.view', 'module' => 'next_visit'],
        ['id' => 'next_visit.manage', 'name' => 'Manage next visit plans', 'slug' => 'next_visit.manage', 'module' => 'next_visit'],
    ];

    public function up(): void
    {
        $ids = [];
        foreach (self::PERMISSIONS as $permission) {
            Permission::query()->updateOrCreate(['id' => $permission['id']], $permission);
            $ids[] = $permission['id'];
        }

        Role::withoutGlobalScopes()
            ->where('slug', 'owner')
            ->where('is_system', true)
            ->orderBy('id')
            ->each(function (Role $role) use ($ids) {
                $role->permissions()->syncWithoutDetaching($ids);
            });
    }

    public function down(): void
    {
        // Keep catalogue rows; only detach from owner roles if rolling back the grant.
        $ids = array_column(self::PERMISSIONS, 'id');

        Role::withoutGlobalScopes()
            ->where('slug', 'owner')
            ->where('is_system', true)
            ->orderBy('id')
            ->each(function (Role $role) use ($ids) {
                $role->permissions()->detach($ids);
            });
    }
};
