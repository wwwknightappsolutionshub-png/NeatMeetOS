<?php

namespace Database\Seeders;

use App\Domains\Booking\Services\SalonReviewService;
use App\Domains\Identity\Models\Tenant;
use Illuminate\Database\Seeder;

class SalonReviewsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'demo-salon')->first();
        if ($tenant === null) {
            return;
        }

        if (\App\Domains\Booking\Models\SalonReview::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->exists()) {
            return;
        }

        app(SalonReviewService::class)->seedForTenant($tenant->id, [
            [
                'author_name' => 'Amelia R.',
                'rating' => 5,
                'body' => 'Absolutely love this salon. The cut was perfect and the team made me feel so welcome.',
            ],
            [
                'author_name' => 'Jordan K.',
                'rating' => 5,
                'body' => 'Booking online was effortless and my colour came out exactly as I hoped. Already rebooked.',
            ],
            [
                'author_name' => 'Priya S.',
                'rating' => 4,
                'body' => 'Great atmosphere and consistently good results. The stylists really listen.',
            ],
            [
                'author_name' => 'Marcus T.',
                'rating' => 5,
                'body' => 'Best fade I have had in London. Quick booking, on-time appointment, brilliant finish.',
            ],
            [
                'author_name' => 'Elena V.',
                'rating' => 5,
                'body' => 'Friendly staff and beautiful space. I tell everyone to book here.',
            ],
        ]);
    }
}
