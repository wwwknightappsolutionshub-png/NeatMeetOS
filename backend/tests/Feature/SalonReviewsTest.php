<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\SalonReview;
use App\Domains\Identity\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class SalonReviewsTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    public function test_public_can_list_and_submit_reviews(): void
    {
        $ctx = $this->seedTenantContext(['booking.view', 'booking.manage']);
        SubscriptionPlan::query()->firstOrCreate(
            ['slug' => 'basic'],
            [
                'name' => 'Basic',
                'billing_interval' => 'monthly',
                'features' => ['booking' => true, 'crm' => true, 'payments' => true],
                'limits' => [],
                'is_active' => true,
            ],
        );

        SalonReview::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'author_name' => 'Seed Guest',
            'rating' => 5,
            'body' => 'Wonderful experience from start to finish.',
            'is_published' => true,
            'display_order' => 1,
        ]);

        $this->withHeader('X-Tenant-Slug', $ctx['tenant']->slug)
            ->getJson('/api/v1/book/reviews')
            ->assertOk()
            ->assertJsonPath('data.0.author_name', 'Seed Guest');

        $this->withHeader('X-Tenant-Slug', $ctx['tenant']->slug)
            ->postJson('/api/v1/book/reviews', [
                'author_name' => 'New Guest',
                'rating' => 4,
                'body' => 'Really friendly team and great cut.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.author_name', 'New Guest');
    }

    public function test_admin_can_update_and_delete_review(): void
    {
        $ctx = $this->seedTenantContext(['booking.view', 'booking.manage']);
        $review = SalonReview::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'author_name' => 'Editable',
            'rating' => 3,
            'body' => 'Decent visit overall, would return again.',
            'is_published' => true,
            'display_order' => 1,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/reviews/'.$review->id, [
                'rating' => 5,
                'is_published' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.is_published', false);

        $this->withTenantAuth($ctx['token'])
            ->deleteJson('/api/v1/admin/reviews/'.$review->id)
            ->assertOk();

        $this->assertDatabaseMissing('salon_reviews', ['id' => $review->id]);
    }
}
