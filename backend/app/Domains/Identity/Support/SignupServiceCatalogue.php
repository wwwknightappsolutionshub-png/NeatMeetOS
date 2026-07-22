<?php

namespace App\Domains\Identity\Support;

/**
 * Generic salon service templates offered during tenant signup.
 */
final class SignupServiceCatalogue
{
    public const BASIC_MAX_SERVICES = 4;

    /**
     * @return list<array{
     *     key: string,
     *     name: string,
     *     category: string,
     *     description: string,
     *     duration_minutes: int,
     *     base_price_cents: int,
     *     selected_by_default: bool,
     *     business_types: list<string>
     * }>
     */
    public static function defaults(): array
    {
        $hairSalon = ['boutique', 'chain', 'other'];
        $barber = ['barbershop', 'other'];
        $spa = ['spa', 'other'];
        $all = ['boutique', 'chain', 'barbershop', 'spa', 'other'];

        return [
            [
                'key' => 'cut_blow_dry',
                'name' => 'Cut & Blow Dry',
                'category' => 'hair',
                'description' => 'Consultation, precision cut, wash, and finish blow-dry.',
                'duration_minutes' => 60,
                'base_price_cents' => 4500,
                'selected_by_default' => true,
                'business_types' => $hairSalon,
            ],
            [
                'key' => 'blow_dry',
                'name' => 'Blow Dry',
                'category' => 'hair',
                'description' => 'Wash and professional finish blow-dry with styling product.',
                'duration_minutes' => 45,
                'base_price_cents' => 3500,
                'selected_by_default' => true,
                'business_types' => $hairSalon,
            ],
            [
                'key' => 'full_colour',
                'name' => 'Full Colour',
                'category' => 'colour',
                'description' => 'All-over colour application with toner and finish.',
                'duration_minutes' => 120,
                'base_price_cents' => 8500,
                'selected_by_default' => true,
                'business_types' => $hairSalon,
            ],
            [
                'key' => 'highlights',
                'name' => 'Highlights',
                'category' => 'colour',
                'description' => 'Foil highlights with toner for brightness and dimension.',
                'duration_minutes' => 150,
                'base_price_cents' => 9500,
                'selected_by_default' => false,
                'business_types' => $hairSalon,
            ],
            [
                'key' => 'balayage',
                'name' => 'Balayage',
                'category' => 'colour',
                'description' => 'Hand-painted balayage for soft, lived-in colour.',
                'duration_minutes' => 180,
                'base_price_cents' => 12000,
                'selected_by_default' => false,
                'business_types' => $hairSalon,
            ],
            [
                'key' => 'root_touch_up',
                'name' => 'Root Touch-Up',
                'category' => 'colour',
                'description' => 'Root colour refresh to cover regrowth.',
                'duration_minutes' => 75,
                'base_price_cents' => 5500,
                'selected_by_default' => false,
                'business_types' => $hairSalon,
            ],
            [
                'key' => 'hair_treatment',
                'name' => 'Hair Treatment',
                'category' => 'care',
                'description' => 'Deep conditioning or repair treatment tailored to hair type.',
                'duration_minutes' => 30,
                'base_price_cents' => 2500,
                'selected_by_default' => true,
                'business_types' => array_values(array_unique([...$hairSalon, ...$spa])),
            ],
            [
                'key' => 'beard_trim',
                'name' => 'Beard Trim',
                'category' => 'barber',
                'description' => 'Shape and tidy beard with hot towel finish.',
                'duration_minutes' => 30,
                'base_price_cents' => 2000,
                'selected_by_default' => true,
                'business_types' => $barber,
            ],
            [
                'key' => 'skin_fade',
                'name' => 'Skin Fade',
                'category' => 'barber',
                'description' => 'Precision skin fade with clipper and scissor finish.',
                'duration_minutes' => 45,
                'base_price_cents' => 3000,
                'selected_by_default' => true,
                'business_types' => $barber,
            ],
            [
                'key' => 'hot_towel_shave',
                'name' => 'Hot Towel Shave',
                'category' => 'barber',
                'description' => 'Traditional hot towel straight-razor shave.',
                'duration_minutes' => 40,
                'base_price_cents' => 2800,
                'selected_by_default' => false,
                'business_types' => $barber,
            ],
            [
                'key' => 'kids_cut',
                'name' => 'Kids Cut',
                'category' => 'hair',
                'description' => 'Children\'s haircut with style finish (under 12).',
                'duration_minutes' => 30,
                'base_price_cents' => 2500,
                'selected_by_default' => false,
                'business_types' => array_values(array_unique([...$hairSalon, ...$barber])),
            ],
            [
                'key' => 'bridal_styling',
                'name' => 'Bridal / Occasion Styling',
                'category' => 'styling',
                'description' => 'Event hair styling including trial-ready finish.',
                'duration_minutes' => 90,
                'base_price_cents' => 7500,
                'selected_by_default' => false,
                'business_types' => $hairSalon,
            ],
            [
                'key' => 'facial',
                'name' => 'Signature Facial',
                'category' => 'spa',
                'description' => 'Cleansing facial with massage and mask tailored to skin type.',
                'duration_minutes' => 60,
                'base_price_cents' => 6500,
                'selected_by_default' => true,
                'business_types' => $spa,
            ],
            [
                'key' => 'massage_60',
                'name' => 'Relaxation Massage (60 min)',
                'category' => 'spa',
                'description' => 'Full-body relaxation massage.',
                'duration_minutes' => 60,
                'base_price_cents' => 7000,
                'selected_by_default' => true,
                'business_types' => $spa,
            ],
            [
                'key' => 'manicure',
                'name' => 'Manicure',
                'category' => 'spa',
                'description' => 'Classic manicure with shape, cuticle care, and polish.',
                'duration_minutes' => 45,
                'base_price_cents' => 3500,
                'selected_by_default' => true,
                'business_types' => $spa,
            ],
            [
                'key' => 'consultation',
                'name' => 'Consultation',
                'category' => 'general',
                'description' => 'In-person colour or style consultation before a major service.',
                'duration_minutes' => 20,
                'base_price_cents' => 0,
                'selected_by_default' => false,
                'business_types' => $all,
            ],
            [
                'key' => 'keratin_smooth',
                'name' => 'Keratin Smooth',
                'category' => 'care',
                'description' => 'Smoothing keratin treatment for frizz control and shine.',
                'duration_minutes' => 150,
                'base_price_cents' => 15000,
                'selected_by_default' => false,
                'business_types' => $hairSalon,
            ],
        ];
    }

    public static function servicesStep(): array
    {
        return [
            'id' => 'services',
            'title' => 'Define your services',
            'description' => 'Pick the services you offer for your business type. Basic plans can add up to '.self::BASIC_MAX_SERVICES.' services — upgrade later to unlock more.',
            'fields' => [
                [
                    'key' => 'services',
                    'label' => 'Services',
                    'type' => 'service_catalogue',
                    'required' => true,
                ],
            ],
        ];
    }
}
