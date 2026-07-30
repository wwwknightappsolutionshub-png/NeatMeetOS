<?php

use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Grant AI Hairstyle permissions to Manager roles (owners already granted).
 * Enables staff with the manager role to use Approved Looks without 403.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'ai_hairstyle.view',
        'ai_hairstyle.manage',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $id) {
            Permission::query()->firstOrCreate(
                ['id' => $id],
                [
                    'name' => $id === 'ai_hairstyle.view'
                        ? 'View AI hairstyle previews'
                        : 'Manage AI hairstyle approvals',
                    'slug' => $id,
                    'module' => 'ai_hairstyle',
                ],
            );
        }

        Role::withoutGlobalScopes()
            ->whereIn('slug', ['owner', 'manager'])
            ->orderBy('id')
            ->each(function (Role $role) {
                $role->permissions()->syncWithoutDetaching(self::PERMISSIONS);
            });
    }

    public function down(): void
    {
        Role::withoutGlobalScopes()
            ->where('slug', 'manager')
            ->orderBy('id')
            ->each(function (Role $role) {
                $role->permissions()->detach(self::PERMISSIONS);
            });
    }
};
