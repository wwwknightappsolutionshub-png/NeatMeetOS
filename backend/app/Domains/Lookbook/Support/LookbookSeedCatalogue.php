<?php

namespace App\Domains\Lookbook\Support;

/**
 * Curated lookbook seed imagery (HTTPS Unsplash CDN).
 *
 * @phpstan-type SeedEntry array{title: string, caption: string, image_url: string}
 */
final class LookbookSeedCatalogue
{
    public const CATEGORIES = ['boutique', 'barbershop', 'spa', 'chain', 'other'];

    /**
     * @return list<SeedEntry>
     */
    public static function forCategory(string $category): array
    {
        $key = in_array($category, self::CATEGORIES, true) ? $category : 'other';

        return self::catalogue()[$key];
    }

    public static function mapBusinessType(?string $businessType): string
    {
        $type = strtolower(trim((string) $businessType));

        return match ($type) {
            'boutique', 'salon', 'hair_salon', 'beauty', 'nails' => 'boutique',
            'barbershop', 'barber', 'mens' => 'barbershop',
            'spa', 'wellness', 'massage' => 'spa',
            'chain', 'franchise', 'multi_location' => 'chain',
            default => 'other',
        };
    }

    /**
     * @return array<string, list<SeedEntry>>
     */
    private static function catalogue(): array
    {
        return [
            'boutique' => [
                ['title' => 'Soft blowout', 'caption' => 'Fresh finish after a colour refresh.', 'image_url' => 'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=900&q=80'],
                ['title' => 'Colour glaze', 'caption' => 'Glossy tone for natural shine.', 'image_url' => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=900&q=80'],
                ['title' => 'Bridal waves', 'caption' => 'Soft waves for special occasions.', 'image_url' => 'https://images.unsplash.com/photo-1519699047748-de8e457a634e?w=900&q=80'],
                ['title' => 'Precision cut', 'caption' => 'Clean lines and easy movement.', 'image_url' => 'https://images.unsplash.com/photo-1492106087820-71f1a00d2b11?w=900&q=80'],
                ['title' => 'Balayage blend', 'caption' => 'Sun-kissed dimension without harsh lines.', 'image_url' => 'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?w=900&q=80'],
                ['title' => 'Studio styling', 'caption' => 'Editorial volume and texture.', 'image_url' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?w=900&q=80'],
                ['title' => 'Fringe refresh', 'caption' => 'Face-framing fringe with soft edges.', 'image_url' => 'https://images.unsplash.com/photo-1595476108010-b4d1f102b1b1?w=900&q=80'],
                ['title' => 'Salon chair glow', 'caption' => 'Calm, polished boutique atmosphere.', 'image_url' => 'https://images.unsplash.com/photo-1633681926022-db1b6ddcea68?w=900&q=80'],
                ['title' => 'Mirror moment', 'caption' => 'Reveal look after finishing spray.', 'image_url' => 'https://images.unsplash.com/photo-1562322140-8baeececf3df?w=900&q=80'],
                ['title' => 'Product shelf', 'caption' => 'Retail favourites for at-home care.', 'image_url' => 'https://images.unsplash.com/photo-1527799820374-dcf8d9d4a388?w=900&q=80'],
                ['title' => 'Curl definition', 'caption' => 'Hydrated curls with soft hold.', 'image_url' => 'https://images.unsplash.com/photo-1605497788044-5a32c7078486?w=900&q=80'],
                ['title' => 'Updo polish', 'caption' => 'Elegant structure for evening events.', 'image_url' => 'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?w=900&q=80'],
                ['title' => 'Toner result', 'caption' => 'Cool, even blonde tone.', 'image_url' => 'https://images.unsplash.com/photo-1516975080664-ed2fc6a32937?w=900&q=80'],
                ['title' => 'Layered movement', 'caption' => 'Light layers that fall naturally.', 'image_url' => 'https://images.unsplash.com/photo-1580618672591-eb180b1a973f?w=900&q=80'],
                ['title' => 'Reception calm', 'caption' => 'Welcoming boutique front-of-house.', 'image_url' => 'https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=900&q=80'],
                ['title' => 'Hand-tied weave', 'caption' => 'Seamless length and density.', 'image_url' => 'https://images.unsplash.com/photo-1519415387726-a6addfb03d64?w=900&q=80'],
                ['title' => 'Shine finish', 'caption' => 'Silk press energy with soft movement.', 'image_url' => 'https://images.unsplash.com/photo-1595476108010-b4d1f102b1b1?w=900&q=80'],
                ['title' => 'Colour consultation', 'caption' => 'Shade mapping before the service.', 'image_url' => 'https://images.unsplash.com/photo-1559599101-f09722fb4948?w=900&q=80'],
                ['title' => 'Texture spray', 'caption' => 'Lived-in finish with light hold.', 'image_url' => 'https://images.unsplash.com/photo-1522338140262-f46f5913618a?w=900&q=80'],
                ['title' => 'Aftercare bag', 'caption' => 'Take-home essentials for lasting results.', 'image_url' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?w=900&q=80'],
            ],
            'barbershop' => [
                ['title' => 'Classic fade', 'caption' => 'Clean taper with sharp finish.', 'image_url' => 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=900&q=80'],
                ['title' => 'Beard sculpt', 'caption' => 'Line-up and beard shape.', 'image_url' => 'https://images.unsplash.com/photo-1621605815971-fbc98d665033?w=900&q=80'],
                ['title' => 'Hot towel shave', 'caption' => 'Traditional straight-razor ritual.', 'image_url' => 'https://images.unsplash.com/photo-1599351431202-1e0f0137899a?w=900&q=80'],
                ['title' => 'Chair side', 'caption' => 'Classic barber chair detail.', 'image_url' => 'https://images.unsplash.com/photo-1585747860715-2ba37e788b70?w=900&q=80'],
                ['title' => 'Clipper work', 'caption' => 'Precision fade in progress.', 'image_url' => 'https://images.unsplash.com/photo-1622286342621-4bd786c2447c?w=900&q=80'],
                ['title' => 'Texture crop', 'caption' => 'Modern crop with matte product.', 'image_url' => 'https://images.unsplash.com/photo-1493256338651-d82fcd028add?w=900&q=80'],
                ['title' => 'Shop interior', 'caption' => 'Warm lights and mirror wall.', 'image_url' => 'https://images.unsplash.com/photo-1585747860715-2ba37e788b70?w=900&q=80'],
                ['title' => 'Neck clean-up', 'caption' => 'Sharp edges around the neckline.', 'image_url' => 'https://images.unsplash.com/photo-1593702277156-6df5b95a537b?w=900&q=80'],
                ['title' => 'Pomade finish', 'caption' => 'Side part with soft shine.', 'image_url' => 'https://images.unsplash.com/photo-1621605815971-fbc98d665033?w=900&q=80'],
                ['title' => 'Tools ready', 'caption' => 'Clippers and combs laid out.', 'image_url' => 'https://images.unsplash.com/photo-1516975080664-ed2fc6a32937?w=900&q=80'],
                ['title' => 'Skin fade', 'caption' => 'Tight blend into the skin.', 'image_url' => 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=900&q=80'],
                ['title' => 'Mustache tidy', 'caption' => 'Groomed mustache and beard edges.', 'image_url' => 'https://images.unsplash.com/photo-1599351431202-1e0f0137899a?w=900&q=80'],
                ['title' => 'Waiting lounge', 'caption' => 'Relaxed space before the chair.', 'image_url' => 'https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=900&q=80'],
                ['title' => 'Mid fade', 'caption' => 'Balanced mid fade with length on top.', 'image_url' => 'https://images.unsplash.com/photo-1622286342621-4bd786c2447c?w=900&q=80'],
                ['title' => 'Line-up detail', 'caption' => 'Crisp forehead and temple line.', 'image_url' => 'https://images.unsplash.com/photo-1593702277156-6df5b95a537b?w=900&q=80'],
                ['title' => 'Grooming shelf', 'caption' => 'Oils, balms, and finishing products.', 'image_url' => 'https://images.unsplash.com/photo-1527799820374-dcf8d9d4a388?w=900&q=80'],
                ['title' => 'After shave', 'caption' => 'Smooth finish with aftershave balm.', 'image_url' => 'https://images.unsplash.com/photo-1621605815971-fbc98d665033?w=900&q=80'],
                ['title' => 'Quiff lift', 'caption' => 'Volume on top with clean sides.', 'image_url' => 'https://images.unsplash.com/photo-1493256338651-d82fcd028add?w=900&q=80'],
                ['title' => 'Barber apron', 'caption' => 'Hands-on craft at the station.', 'image_url' => 'https://images.unsplash.com/photo-1585747860715-2ba37e788b70?w=900&q=80'],
                ['title' => 'Final brush', 'caption' => 'Dust-off and mirror check.', 'image_url' => 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=900&q=80'],
            ],
            'spa' => [
                ['title' => 'Treatment room', 'caption' => 'Quiet space prepared for service.', 'image_url' => 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?w=900&q=80'],
                ['title' => 'Facial ritual', 'caption' => 'Deep cleanse and glow therapy.', 'image_url' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?w=900&q=80'],
                ['title' => 'Massage oils', 'caption' => 'Warm oils for muscle release.', 'image_url' => 'https://images.unsplash.com/photo-1519823551278-64ac92734fb1?w=900&q=80'],
                ['title' => 'Candle calm', 'caption' => 'Soft light and spa atmosphere.', 'image_url' => 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?w=900&q=80'],
                ['title' => 'Stone therapy', 'caption' => 'Heated stones for deep relaxation.', 'image_url' => 'https://images.unsplash.com/photo-1600334129128-685c5582fd35?w=900&q=80'],
                ['title' => 'Herbal steam', 'caption' => 'Aromatic steam for skin comfort.', 'image_url' => 'https://images.unsplash.com/photo-1515377905703-c4788e51af15?w=900&q=80'],
                ['title' => 'Manicure tray', 'caption' => 'Neat tools for nail care.', 'image_url' => 'https://images.unsplash.com/photo-1604654894610-df63bc536371?w=900&q=80'],
                ['title' => 'Pedicure soak', 'caption' => 'Warm soak before detail work.', 'image_url' => 'https://images.unsplash.com/photo-1519014816548-bf5fe059798b?w=900&q=80'],
                ['title' => 'Body scrub', 'caption' => 'Exfoliation for smoother skin.', 'image_url' => 'https://images.unsplash.com/photo-1512290923902-8a9f81dc236c?w=900&q=80'],
                ['title' => 'Relax lounge', 'caption' => 'Quiet recovery after treatment.', 'image_url' => 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?w=900&q=80'],
                ['title' => 'Facial mask', 'caption' => 'Hydrating mask for radiance.', 'image_url' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?w=900&q=80'],
                ['title' => 'Aromatherapy', 'caption' => 'Essential oils for mood balance.', 'image_url' => 'https://images.unsplash.com/photo-1608571423902-eed4a5ad8108?w=900&q=80'],
                ['title' => 'Spa towels', 'caption' => 'Fresh linens ready for guests.', 'image_url' => 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?w=900&q=80'],
                ['title' => 'Scalp treatment', 'caption' => 'Gentle massage for scalp health.', 'image_url' => 'https://images.unsplash.com/photo-1519823551278-64ac92734fb1?w=900&q=80'],
                ['title' => 'Wellness kit', 'caption' => 'At-home care recommendations.', 'image_url' => 'https://images.unsplash.com/photo-1556228578-0d85b1a4d571?w=900&q=80'],
                ['title' => 'Foot care', 'caption' => 'Detail pedicure finish.', 'image_url' => 'https://images.unsplash.com/photo-1519014816548-bf5fe059798b?w=900&q=80'],
                ['title' => 'Glow serum', 'caption' => 'Serum layer for lasting hydration.', 'image_url' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03?w=900&q=80'],
                ['title' => 'Massage table', 'caption' => 'Prepared bed for full-body work.', 'image_url' => 'https://images.unsplash.com/photo-1600334129128-685c5582fd35?w=900&q=80'],
                ['title' => 'Nail colour', 'caption' => 'Polished colour for hands and toes.', 'image_url' => 'https://images.unsplash.com/photo-1604654894610-df63bc536371?w=900&q=80'],
                ['title' => 'Quiet corridor', 'caption' => 'Soft transition into treatment rooms.', 'image_url' => 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?w=900&q=80'],
            ],
            'chain' => [
                ['title' => 'Reception desk', 'caption' => 'Consistent welcome across locations.', 'image_url' => 'https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=900&q=80'],
                ['title' => 'Open floor', 'caption' => 'Bright multi-station floor plan.', 'image_url' => 'https://images.unsplash.com/photo-1633681926022-db1b6ddcea68?w=900&q=80'],
                ['title' => 'Brand wall', 'caption' => 'Clear identity at every site.', 'image_url' => 'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=900&q=80'],
                ['title' => 'Team brief', 'caption' => 'Shared standards before open.', 'image_url' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?w=900&q=80'],
                ['title' => 'Service bay', 'caption' => 'Repeatable station setup.', 'image_url' => 'https://images.unsplash.com/photo-1562322140-8baeececf3df?w=900&q=80'],
                ['title' => 'Retail wall', 'caption' => 'Uniform retail merchandising.', 'image_url' => 'https://images.unsplash.com/photo-1527799820374-dcf8d9d4a388?w=900&q=80'],
                ['title' => 'Booking screen', 'caption' => 'Digital check-in and queue flow.', 'image_url' => 'https://images.unsplash.com/photo-1556761175-5973dc0f32e7?w=900&q=80'],
                ['title' => 'Colour bay', 'caption' => 'Dedicated colour processing area.', 'image_url' => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=900&q=80'],
                ['title' => 'Wash stations', 'caption' => 'Efficient shampoo bay layout.', 'image_url' => 'https://images.unsplash.com/photo-1580618672591-eb180b1a973f?w=900&q=80'],
                ['title' => 'Kids corner', 'caption' => 'Family-friendly waiting space.', 'image_url' => 'https://images.unsplash.com/photo-1519415387726-a6addfb03d64?w=900&q=80'],
                ['title' => 'Staff lockers', 'caption' => 'Back-of-house readiness.', 'image_url' => 'https://images.unsplash.com/photo-1497366216548-37526070297c?w=900&q=80'],
                ['title' => 'Promo display', 'caption' => 'Seasonal offer presentation.', 'image_url' => 'https://images.unsplash.com/photo-1559599101-f09722fb4948?w=900&q=80'],
                ['title' => 'Finishing aisle', 'caption' => 'Styling aisle with consistent tools.', 'image_url' => 'https://images.unsplash.com/photo-1522338140262-f46f5913618a?w=900&q=80'],
                ['title' => 'Evening glow', 'caption' => 'Evening lighting across the floor.', 'image_url' => 'https://images.unsplash.com/photo-1633681926022-db1b6ddcea68?w=900&q=80'],
                ['title' => 'Membership desk', 'caption' => 'Plan and package conversations.', 'image_url' => 'https://images.unsplash.com/photo-1556761175-b413da4baf72?w=900&q=80'],
                ['title' => 'Training board', 'caption' => 'Shared skill standards.', 'image_url' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?w=900&q=80'],
                ['title' => 'Corridor mirrors', 'caption' => 'Clean circulation between bays.', 'image_url' => 'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=900&q=80'],
                ['title' => 'Retail till', 'caption' => 'Click-and-collect ready counter.', 'image_url' => 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=900&q=80'],
                ['title' => 'Group style', 'caption' => 'Lookbook energy for campaigns.', 'image_url' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?w=900&q=80'],
                ['title' => 'Closing checklist', 'caption' => 'End-of-day consistency across sites.', 'image_url' => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=900&q=80'],
            ],
            'other' => [
                ['title' => 'Studio light', 'caption' => 'Clean service space with soft light.', 'image_url' => 'https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=900&q=80'],
                ['title' => 'Chair ready', 'caption' => 'Prepared station for the next guest.', 'image_url' => 'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=900&q=80'],
                ['title' => 'Style finish', 'caption' => 'Polished look after the appointment.', 'image_url' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?w=900&q=80'],
                ['title' => 'Product detail', 'caption' => 'Care products for lasting results.', 'image_url' => 'https://images.unsplash.com/photo-1527799820374-dcf8d9d4a388?w=900&q=80'],
                ['title' => 'Colour tray', 'caption' => 'Mixing for custom tone work.', 'image_url' => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=900&q=80'],
                ['title' => 'Wash bay', 'caption' => 'Comfortable rinse and condition.', 'image_url' => 'https://images.unsplash.com/photo-1580618672591-eb180b1a973f?w=900&q=80'],
                ['title' => 'Mirror check', 'caption' => 'Final reveal with the guest.', 'image_url' => 'https://images.unsplash.com/photo-1562322140-8baeececf3df?w=900&q=80'],
                ['title' => 'Tools tidy', 'caption' => 'Organised tools between clients.', 'image_url' => 'https://images.unsplash.com/photo-1516975080664-ed2fc6a32937?w=900&q=80'],
                ['title' => 'Waiting seat', 'caption' => 'Calm space before service begins.', 'image_url' => 'https://images.unsplash.com/photo-1497366216548-37526070297c?w=900&q=80'],
                ['title' => 'Retail shelf', 'caption' => 'Take-home favourites on display.', 'image_url' => 'https://images.unsplash.com/photo-1556228578-0d85b1a4d571?w=900&q=80'],
                ['title' => 'Texture play', 'caption' => 'Movement and soft definition.', 'image_url' => 'https://images.unsplash.com/photo-1522338140262-f46f5913618a?w=900&q=80'],
                ['title' => 'Skin glow', 'caption' => 'Fresh finish after facial care.', 'image_url' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?w=900&q=80'],
                ['title' => 'Groom detail', 'caption' => 'Clean edges and tidy finish.', 'image_url' => 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=900&q=80'],
                ['title' => 'Spa calm', 'caption' => 'Quiet treatment atmosphere.', 'image_url' => 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?w=900&q=80'],
                ['title' => 'Nail polish', 'caption' => 'Colour and care for hands.', 'image_url' => 'https://images.unsplash.com/photo-1604654894610-df63bc536371?w=900&q=80'],
                ['title' => 'Lobby light', 'caption' => 'First impression at the door.', 'image_url' => 'https://images.unsplash.com/photo-1633681926022-db1b6ddcea68?w=900&q=80'],
                ['title' => 'Consultation', 'caption' => 'Talking through the next visit.', 'image_url' => 'https://images.unsplash.com/photo-1559599101-f09722fb4948?w=900&q=80'],
                ['title' => 'Aftercare tip', 'caption' => 'Guidance for results at home.', 'image_url' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03?w=900&q=80'],
                ['title' => 'Team craft', 'caption' => 'Hands-on expertise in action.', 'image_url' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?w=900&q=80'],
                ['title' => 'Booking ready', 'caption' => 'Easy next-visit planning.', 'image_url' => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=900&q=80'],
            ],
        ];
    }
}
