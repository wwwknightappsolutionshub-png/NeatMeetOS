<?php

namespace Database\Seeders;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientConsentRecord;
use App\Domains\Crm\Models\ClientDocument;
use App\Domains\Crm\Models\ClientFormula;
use App\Domains\Crm\Models\ClientNote;
use App\Domains\Crm\Models\ClientPhoto;
use App\Domains\Crm\Models\ClientTag;
use App\Domains\Crm\Models\ClientTimelineEvent;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Seeder;

class CrmDemoSeeder extends Seeder
{
    public function run(Tenant $tenant, Location $location, TeamMember $teamMember, User $user): void
    {
        $vipTag = ClientTag::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'VIP',
            'slug' => 'vip',
            'color' => '#f59e0b',
        ]);

        $regularTag = ClientTag::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Regular',
            'slug' => 'regular',
            'color' => '#3b82f6',
        ]);

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Alex',
            'last_name' => 'Taylor',
            'email' => 'alex.taylor@example.com',
            'phone' => '+447700900123',
            'primary_location_id' => $location->id,
            'preferred_team_member_id' => $teamMember->id,
            'preferences' => [
                'appointment_notes' => 'Afternoon appointments preferred',
                'communication_channel' => 'email',
            ],
            'loyalty_display_status' => 'none',
            'is_active' => true,
        ]);
        $client->tags()->attach([$vipTag->id, $regularTag->id]);

        Client::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Jordan',
            'last_name' => 'Lee',
            'email' => 'jordan.lee@example.com',
            'phone' => '+447700900456',
            'primary_location_id' => $location->id,
            'is_active' => true,
        ]);

        ClientNote::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'author_team_member_id' => $teamMember->id,
            'note_type' => ClientNote::TYPE_GENERAL,
            'body' => 'Prefers afternoon appointments. Sensitive scalp — patch test on file.',
        ]);

        ClientConsentRecord::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'consent_type' => ClientConsentRecord::TYPE_MARKETING_EMAIL,
            'granted' => true,
            'source' => ClientConsentRecord::SOURCE_IN_PERSON,
            'actor_user_id' => $user->id,
            'recorded_at' => now()->subDays(7),
        ]);

        ClientConsentRecord::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'consent_type' => ClientConsentRecord::TYPE_MARKETING_SMS,
            'granted' => false,
            'source' => ClientConsentRecord::SOURCE_STAFF_ENTRY,
            'actor_user_id' => $user->id,
            'recorded_at' => now()->subDay(),
        ]);

        ClientTimelineEvent::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'event_type' => ClientTimelineEvent::EVENT_CLIENT_CREATED,
            'title' => 'Client profile created',
            'description' => 'Alex Taylor',
            'actor_user_id' => $user->id,
            'occurred_at' => now()->subDays(30),
        ]);

        ClientTimelineEvent::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'event_type' => ClientTimelineEvent::EVENT_NOTE_ADDED,
            'title' => 'Note added',
            'description' => 'Prefers afternoon appointments.',
            'actor_user_id' => $user->id,
            'occurred_at' => now()->subDays(2),
        ]);

        ClientFormula::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'title' => 'Root colour — Dec 2025',
            'formula_body' => "6.0 + 6.1 (1:1)\n20 vol developer\nProcess 35 mins",
            'category' => ClientFormula::CATEGORY_COLOUR,
            'service_context' => 'Full head colour',
            'recorded_by_team_member_id' => $teamMember->id,
            'is_active' => true,
        ]);

        ClientPhoto::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'storage_path' => '/storage/demo/clients/alex-reference.jpg',
            'category' => ClientPhoto::CATEGORY_REFERENCE,
            'caption' => 'Reference colour — warm brown',
            'uploaded_by_team_member_id' => $teamMember->id,
            'is_active' => true,
        ]);

        ClientDocument::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'title' => 'Patch test record',
            'document_type' => ClientDocument::TYPE_REFERENCE,
            'storage_path' => '/storage/demo/clients/alex-patch-test.pdf',
            'description' => 'Signed patch test — no reaction',
            'uploaded_by_team_member_id' => $teamMember->id,
            'is_active' => true,
        ]);
    }
}
