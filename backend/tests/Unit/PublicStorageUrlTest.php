<?php

namespace Tests\Unit;

use App\Shared\Support\PublicStorageUrl;
use Tests\TestCase;

class PublicStorageUrlTest extends TestCase
{
    public function test_from_disk_path_uses_app_url(): void
    {
        config(['app.url' => 'http://localhost:8000']);

        $this->assertSame(
            'http://localhost:8000/storage/branding/t1/emblems/a.jpg',
            PublicStorageUrl::fromDiskPath('branding/t1/emblems/a.jpg'),
        );
    }

    public function test_normalize_rewrites_storage_path_to_app_url(): void
    {
        config(['app.url' => 'http://localhost:8000']);

        $this->assertSame(
            'http://localhost:8000/storage/branding/t1/emblems/a.jpg',
            PublicStorageUrl::normalize('http://localhost/storage/branding/t1/emblems/a.jpg'),
        );

        $this->assertSame(
            'http://localhost:8000/storage/branding/t1/emblems/a.jpg',
            PublicStorageUrl::normalize('/storage/branding/t1/emblems/a.jpg'),
        );
    }
}
