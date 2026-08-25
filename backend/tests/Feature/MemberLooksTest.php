<?php

namespace Tests\Feature;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientLook;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class MemberLooksTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    public function test_member_can_upload_up_to_four_looks_and_delete(): void
    {
        Storage::fake('public');

        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);
        app(TenantContext::class)->set($ctx['tenant']);

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Look',
            'last_name' => 'Owner',
            'email' => 'looks-owner@example.test',
            'phone' => '+447700901001',
            'is_active' => true,
            'primary_location_id' => $ctx['location']->id,
        ]);

        $other = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Other',
            'last_name' => 'Member',
            'email' => 'looks-other@example.test',
            'phone' => '+447700901002',
            'is_active' => true,
            'primary_location_id' => $ctx['location']->id,
        ]);

        $token = $this->memberLoginViaOtp(
            $ctx['tenant']->slug,
            'looks-owner@example.test',
            '+447700901001',
        );
        $headers = [
            'X-Tenant-Slug' => $ctx['tenant']->slug,
            'Authorization' => 'Bearer '.$token,
        ];

        $lookIds = [];
        for ($i = 0; $i < 4; $i++) {
            $response = $this->withHeaders($headers)->post(
                '/api/v1/member/looks',
                [
                    'image' => UploadedFile::fake()->image("look-{$i}.jpg", 400, 400),
                    'caption' => "Look {$i}",
                ],
            );
            $response->assertCreated();
            $lookIds[] = $response->json('data.id');
        }

        $this->withHeaders($headers)
            ->post('/api/v1/member/looks', [
                'image' => UploadedFile::fake()->image('look-5.jpg', 400, 400),
            ])
            ->assertStatus(422);

        $this->withHeaders($headers)
            ->getJson('/api/v1/member/looks')
            ->assertOk()
            ->assertJsonCount(4, 'data');

        $this->assertSame(4, ClientLook::query()->where('client_id', $client->id)->count());

        $otherToken = $this->memberLoginViaOtp(
            $ctx['tenant']->slug,
            'looks-other@example.test',
            '+447700901002',
        );
        $this->withHeaders([
            'X-Tenant-Slug' => $ctx['tenant']->slug,
            'Authorization' => 'Bearer '.$otherToken,
        ])
            ->deleteJson('/api/v1/member/looks/'.$lookIds[0])
            ->assertNotFound();

        $this->assertDatabaseHas('client_looks', ['id' => $lookIds[0], 'client_id' => $client->id]);

        $this->withHeaders($headers)
            ->deleteJson('/api/v1/member/looks/'.$lookIds[0])
            ->assertOk();

        $this->assertDatabaseMissing('client_looks', ['id' => $lookIds[0]]);
        $this->assertSame(3, ClientLook::query()->where('client_id', $client->id)->count());
        $this->assertSame(0, ClientLook::query()->where('client_id', $other->id)->count());

        $this->withHeaders($headers)
            ->post('/api/v1/member/looks', [
                'image' => UploadedFile::fake()->image('look-slot.jpg', 400, 400),
            ])
            ->assertCreated();

        $this->assertSame(4, ClientLook::query()->where('client_id', $client->id)->count());
    }
}
