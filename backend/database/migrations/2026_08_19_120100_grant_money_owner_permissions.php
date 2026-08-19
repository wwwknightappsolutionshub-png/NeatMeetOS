<?php

use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const PERMISSIONS = [
        ['id' => 'money.view', 'name' => 'View my money', 'slug' => 'money.view', 'module' => 'money'],
        ['id' => 'money.manage', 'name' => 'Manage my money', 'slug' => 'money.manage', 'module' => 'money'],
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
