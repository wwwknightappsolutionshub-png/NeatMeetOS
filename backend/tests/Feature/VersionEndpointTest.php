<?php

namespace Tests\Feature;

use Tests\TestCase;

class VersionEndpointTest extends TestCase
{
    public function test_version_endpoint_returns_api_metadata(): void
    {
        $response = $this->getJson('/api/v1/version');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'NeatMeet OS API')
            ->assertJsonPath('data.api', 'v1');
    }
}
