<?php

namespace App\Domains\AiHairstyle\Contracts;

use App\Domains\AiHairstyle\Models\AiHairstyleSession;

interface AiHairstyleProvider
{
    public function name(): string;

    /**
     * Generate composite previews from an ephemeral local selfie path (P1: never keep original).
     *
     * @return list<array{
     *     composite_image_url: string,
     *     style_label?: string|null,
     *     style_key?: string|null,
     *     sort_order?: int,
     *     provider_meta?: array<string, mixed>|null
     * }>
     */
    public function generate(AiHairstyleSession $session, string $selfieAbsolutePath): array;
}
