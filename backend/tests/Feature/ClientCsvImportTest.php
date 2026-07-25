<?php

namespace Tests\Feature;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientConsentRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class ClientCsvImportTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_preview_suggests_column_mapping(): void
    {
        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);
        Sanctum::actingAs($ctx['user']);

        $csv = "Name,Email,Phone\nAda Lovelace,ada@example.test,+447700900123\n";
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

        $response = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->post('/api/v1/admin/clients/import/preview', [
                'file' => $file,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.row_count', 1)
            ->assertJsonPath('data.suggested_mapping.name', 'Name')
            ->assertJsonPath('data.suggested_mapping.email', 'Email')
            ->assertJsonPath('data.suggested_mapping.phone', 'Phone');
    }

    public function test_import_creates_clients_with_import_consent_and_dedupes(): void
    {
        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);
        Sanctum::actingAs($ctx['user']);

        Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Existing',
            'last_name' => 'Client',
            'email' => 'existing@example.test',
            'phone' => '+447700900999',
            'is_active' => true,
        ]);

        $csv = implode("\n", [
            'First Name,Last Name,Email,Mobile',
            'Ada,Lovelace,ada@example.test,+44 7700 900111',
            'Charles,Babbage,charles@example.test,+447700900222',
            'Dup,Email,existing@example.test,+447700900333',
            'Dup,Phone,other@example.test,+447700900999',
            'Bad,,not-an-email,',
            '',
        ])."\n";

        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

        $response = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->post('/api/v1/admin/clients/import', [
                'file' => $file,
                'mapping' => json_encode([
                    'first_name' => 'First Name',
                    'last_name' => 'Last Name',
                    'email' => 'Email',
                    'phone' => 'Mobile',
                    'name' => null,
                ]),
                'grant_privacy_contact' => true,
                'grant_marketing_sms' => true,
                'grant_marketing_email' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.skipped_duplicates', 2)
            ->assertJsonPath('data.skipped_invalid', 1);

        $this->assertDatabaseHas('clients', [
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Ada',
            'email' => 'ada@example.test',
            'phone' => '+447700900111',
        ]);

        $ada = Client::withoutGlobalScopes()
            ->where('tenant_id', $ctx['tenant']->id)
            ->where('email', 'ada@example.test')
            ->first();

        $this->assertNotNull($ada);
        $this->assertDatabaseHas('client_consent_records', [
            'client_id' => $ada->id,
            'consent_type' => ClientConsentRecord::TYPE_PRIVACY_CONTACT,
            'granted' => true,
            'source' => ClientConsentRecord::SOURCE_IMPORT,
        ]);
        $this->assertDatabaseHas('client_consent_records', [
            'client_id' => $ada->id,
            'consent_type' => ClientConsentRecord::TYPE_MARKETING_SMS,
            'granted' => true,
            'source' => ClientConsentRecord::SOURCE_IMPORT,
        ]);
        $this->assertDatabaseMissing('client_consent_records', [
            'client_id' => $ada->id,
            'consent_type' => ClientConsentRecord::TYPE_MARKETING_EMAIL,
            'granted' => true,
        ]);
    }

    public function test_import_requires_crm_manage(): void
    {
        $ctx = $this->seedTenantContext(['crm.view']);
        Sanctum::actingAs($ctx['user']);

        $file = UploadedFile::fake()->createWithContent(
            'contacts.csv',
            "Name,Phone\nAda,+447700900111\n",
        );

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->post('/api/v1/admin/clients/import', [
                'file' => $file,
                'mapping' => json_encode([
                    'name' => 'Name',
                    'phone' => 'Phone',
                ]),
            ])
            ->assertForbidden();
    }

    public function test_import_is_tenant_scoped_for_duplicates(): void
    {
        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);
        Sanctum::actingAs($ctx['user']);

        Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'first_name' => 'Other',
            'email' => 'shared@example.test',
            'phone' => '+447700900555',
            'is_active' => true,
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'contacts.csv',
            "Name,Email,Phone\nLocal User,shared@example.test,+447700900555\n",
        );

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->post('/api/v1/admin/clients/import', [
                'file' => $file,
                'mapping' => json_encode([
                    'name' => 'Name',
                    'email' => 'Email',
                    'phone' => 'Phone',
                ]),
            ])
            ->assertOk()
            ->assertJsonPath('data.created', 1);

        $this->assertDatabaseHas('clients', [
            'tenant_id' => $ctx['tenant']->id,
            'email' => 'shared@example.test',
        ]);
    }
}
