<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SignupAddressLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_address_lookup_returns_suggestions_for_uk_postcode(): void
    {
        Http::fake([
            'api.postcodes.io/*' => Http::response([
                'status' => 200,
                'result' => [
                    'postcode' => 'SW1A 1AA',
                    'admin_district' => 'Westminster',
                    'parish' => 'Westminster',
                    'region' => 'London',
                ],
            ], 200),
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'display_name' => '10 Downing Street, Westminster, SW1A 1AA, United Kingdom',
                    'address' => [
                        'house_number' => '10',
                        'road' => 'Downing Street',
                        'city' => 'London',
                        'postcode' => 'SW1A 1AA',
                        'country_code' => 'gb',
                    ],
                ],
            ], 200),
        ]);

        $this->getJson('/api/v1/signup/address-lookup?postcode=SW1A1AA')
            ->assertOk()
            ->assertJsonPath('data.formatted_postcode', 'SW1A 1AA')
            ->assertJsonFragment(['address_line1' => '10 Downing Street']);
    }
}
