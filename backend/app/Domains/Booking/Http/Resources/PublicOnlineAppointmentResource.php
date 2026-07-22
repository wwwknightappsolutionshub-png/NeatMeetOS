<?php

namespace App\Domains\Booking\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public booking confirmation / manage payload (includes manage token).
 */
class PublicOnlineAppointmentResource extends JsonResource
{
    /**
     * @param  array{manage_path?: string, manage_url?: string}|null  $manage
     */
    public function __construct($resource, private readonly ?array $manage = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $base = (new AppointmentResource($this->resource))->toArray($request);

        return array_merge($base, [
            'public_manage_token' => $this->public_manage_token,
            'manage_path' => $this->manage['manage_path'] ?? null,
            'manage_url' => $this->manage['manage_url'] ?? null,
        ]);
    }
}
