<?php

namespace Database\Seeders;

use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Integrations\Enums\ProviderAccountStatus;
use App\Domains\Integrations\Enums\ProviderCategory;
use App\Domains\Integrations\Enums\ProviderDriver;
use App\Domains\Integrations\Models\ProviderAccount;

class IntegrationsDemoSeeder
{
    public function run(Tenant $tenant, TeamMember $teamMember): void
    {
        $defaults = [
            [
                'name' => 'Simulation Email',
                'category' => ProviderCategory::EMAIL,
                'driver' => ProviderDriver::SIMULATION,
                'from_name' => 'Demo Salon',
                'from_address' => 'hello@demo.neatmeet.local',
            ],
            [
                'name' => 'Simulation SMS',
                'category' => ProviderCategory::SMS,
                'driver' => ProviderDriver::SIMULATION,
                'phone_number' => '+440000000000',
            ],
            [
                'name' => 'Simulation Payment Gateway',
                'category' => ProviderCategory::PAYMENT_GATEWAY,
                'driver' => ProviderDriver::SIMULATION,
            ],
        ];

        foreach ($defaults as $row) {
            ProviderAccount::withoutGlobalScopes()->create(array_merge($row, [
                'tenant_id' => $tenant->id,
                'status' => ProviderAccountStatus::ACTIVE,
                'is_default' => true,
                'created_by_team_member_id' => $teamMember->id,
                'updated_by_team_member_id' => $teamMember->id,
            ]));
        }
    }
}
