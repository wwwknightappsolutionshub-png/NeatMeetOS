<?php

namespace Database\Seeders;

use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\Workspace;
use App\Domains\Staff\Models\StaffAbsence;
use App\Domains\Staff\Models\StaffAvailabilityRule;
use App\Domains\Staff\Models\StaffProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffDemoSeeder extends Seeder
{
    public function run(
        Tenant $tenant,
        \App\Domains\Identity\Models\Location $location,
        Workspace $workspace,
        TeamMember $ownerMember,
    ): void {
        StaffProfile::withoutGlobalScopes()->updateOrCreate(
            ['team_member_id' => $ownerMember->id],
            [
                'tenant_id' => $tenant->id,
                'is_bookable' => true,
                'show_in_online_booking' => true,
                'accepts_walk_ins' => false,
                'booking_display_name' => 'Demo Owner',
                'internal_notes' => 'Owner also takes colour clients on Wednesdays.',
                'default_workspace_id' => $workspace->id,
                'min_lead_time_minutes' => 60,
                'buffer_minutes' => 15,
            ],
        );

        $ownerMember->operatingLocations()->syncWithoutDetaching([$location->id]);

        StaffAvailabilityRule::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'team_member_id' => $ownerMember->id,
            'location_id' => $location->id,
            'workspace_id' => $workspace->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '13:00',
            'is_active' => true,
        ]);

        StaffAvailabilityRule::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'team_member_id' => $ownerMember->id,
            'location_id' => $location->id,
            'workspace_id' => $workspace->id,
            'day_of_week' => 1,
            'start_time' => '14:00',
            'end_time' => '18:00',
            'is_active' => true,
        ]);

        StaffAvailabilityRule::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'team_member_id' => $ownerMember->id,
            'location_id' => $location->id,
            'workspace_id' => null,
            'day_of_week' => 3,
            'start_time' => '10:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        StaffAbsence::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'team_member_id' => $ownerMember->id,
            'category' => StaffAbsence::CATEGORY_HOLIDAY,
            'starts_at' => now()->addWeeks(2)->startOfDay()->setTime(0, 0),
            'ends_at' => now()->addWeeks(2)->addDays(4)->endOfDay(),
            'note' => 'Annual leave',
            'status' => StaffAbsence::STATUS_ACTIVE,
        ]);

        $stylistUser = User::factory()->create([
            'name' => 'Sam Stylist',
            'email' => 'stylist@demo.neatmeet.local',
            'password' => Hash::make('password'),
        ]);

        $stylist = TeamMember::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $stylistUser->id,
            'first_name' => 'Sam',
            'last_name' => 'Stylist',
            'employment_type' => TeamMember::EMPLOYMENT_EMPLOYEE,
            'display_name' => 'Sam Stylist',
            'primary_location_id' => $location->id,
            'is_active' => true,
        ]);

        $stylist->workspaces()->attach($workspace->id);

        StaffProfile::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'team_member_id' => $stylist->id,
            'is_bookable' => true,
            'show_in_online_booking' => true,
            'accepts_walk_ins' => true,
            'booking_display_name' => 'Sam',
            'default_workspace_id' => $workspace->id,
        ]);

        $stylist->operatingLocations()->attach($location->id);

        StaffAvailabilityRule::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'team_member_id' => $stylist->id,
            'location_id' => $location->id,
            'workspace_id' => $workspace->id,
            'day_of_week' => 2,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        foreach ([4, 5, 6] as $day) {
            StaffAvailabilityRule::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'team_member_id' => $stylist->id,
                'location_id' => $location->id,
                'workspace_id' => $workspace->id,
                'day_of_week' => $day,
                'start_time' => '10:00',
                'end_time' => '16:00',
                'is_active' => true,
            ]);
        }
    }
}
