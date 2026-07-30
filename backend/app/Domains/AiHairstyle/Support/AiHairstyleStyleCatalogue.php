<?php

namespace App\Domains\AiHairstyle\Support;

/**
 * Shared style catalogue for stub + Replicate providers.
 */
final class AiHairstyleStyleCatalogue
{
    /**
     * @return list<array{key: string, label: string, accent: string, prompt: string}>
     */
    public static function styles(): array
    {
        return [
            [
                'key' => 'classic_cut',
                'label' => 'Classic cut',
                'accent' => '#3f5d4a',
                'prompt' => 'professional studio portrait of the same person with a classic neat haircut, natural lighting, photorealistic',
            ],
            [
                'key' => 'textured_crop',
                'label' => 'Textured crop',
                'accent' => '#5b4a3f',
                'prompt' => 'professional studio portrait of the same person with a textured crop hairstyle, natural lighting, photorealistic',
            ],
            [
                'key' => 'soft_layers',
                'label' => 'Soft layers',
                'accent' => '#4a3f5d',
                'prompt' => 'professional studio portrait of the same person with soft layered hair, natural lighting, photorealistic',
            ],
            [
                'key' => 'bold_fringe',
                'label' => 'Bold fringe',
                'accent' => '#3f4a5d',
                'prompt' => 'professional studio portrait of the same person with a bold fringe hairstyle, natural lighting, photorealistic',
            ],
        ];
    }
}
