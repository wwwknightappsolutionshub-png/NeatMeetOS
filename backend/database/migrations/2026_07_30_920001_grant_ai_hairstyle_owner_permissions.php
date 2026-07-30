<?php

use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Seed AI Hairstyle permissions and grant them to system Owner roles.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        ['id' => 'ai_hairstyle.view', 'name' => 'View AI hairstyle previews', 'slug' => 'ai_hairstyle.view', 'module' => 'ai_hairstyle'],
        ['id' => 'ai_hairstyle.manage', 'name' => 'Manage AI hairstyle approvals', 'slug' => 'ai_hairstyle.manage', 'module' => 'ai_hairstyle'],
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
