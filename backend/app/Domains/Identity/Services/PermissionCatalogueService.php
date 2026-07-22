<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\Permission;
use Illuminate\Support\Collection;

class PermissionCatalogueService
{
    public function listGrouped(): Collection
    {
        return Permission::query()
            ->orderBy('module')
            ->orderBy('name')
            ->get()
            ->groupBy('module');
    }

    public function listAll(): Collection
    {
        return Permission::query()
            ->orderBy('module')
            ->orderBy('name')
            ->get();
    }
}
