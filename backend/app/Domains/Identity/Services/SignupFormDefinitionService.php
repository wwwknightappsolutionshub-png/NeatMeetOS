<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\PlatformSignupFormDefinition;
use App\Domains\Identity\Models\SubscriptionPlan;
use App\Domains\Identity\Support\SignupServiceCatalogue;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SignupFormDefinitionService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public static function defaultSteps(): array
    {
        return [
            [
                'id' => 'business',
                'title' => 'Your business',
                'description' => 'Tell us about the salon you are opening on NeatMeet OS.',
                'fields' => [
                    [
                        'key' => 'business_name',
                        'label' => 'Salon name',
                        'type' => 'text',
                        'required' => true,
                        'placeholder' => 'e.g. Bloom Hair Studio',
                    ],
                    [
                        'key' => 'trading_name',
                        'label' => 'Trading name',
                        'type' => 'text',
                        'required' => false,
                        'placeholder' => 'Name customers know you by, if different from the salon name',
                    ],
                    [
                        'key' => 'slug',
                        'label' => 'Public booking URL slug',
                        'type' => 'text',
                        'required' => false,
                        'placeholder' => 'Auto-filled from your salon name',
                        'help' => 'Auto-generated from the salon name. Edit only if you want a custom booking URL.',
                    ],
                    [
                        'key' => 'business_type',
                        'label' => 'Business type',
                        'type' => 'select',
                        'required' => true,
                        'options' => [
                            ['value' => 'boutique', 'label' => 'Boutique / independent'],
                            ['value' => 'chain', 'label' => 'Multi-location chain'],
                            ['value' => 'barbershop', 'label' => 'Barbershop'],
                            ['value' => 'spa', 'label' => 'Spa / wellness'],
                            ['value' => 'other', 'label' => 'Other'],
                        ],
                    ],
                    [
                        'key' => 'timezone',
                        'label' => 'Timezone',
                        'type' => 'select',
                        'required' => true,
                        'options' => [
                            ['value' => 'Europe/London', 'label' => 'Europe/London'],
                            ['value' => 'Europe/Dublin', 'label' => 'Europe/Dublin'],
                            ['value' => 'Africa/Lagos', 'label' => 'Africa/Lagos'],
                            ['value' => 'Africa/Johannesburg', 'label' => 'Africa/Johannesburg'],
                            ['value' => 'America/New_York', 'label' => 'America/New_York'],
                        ],
                    ],
                ],
            ],
            SignupServiceCatalogue::servicesStep(),
            [
                'id' => 'owner',
                'title' => 'Owner account',
                'description' => 'This person becomes the salon owner and receives the activation email.',
                'fields' => [
                    [
                        'key' => 'owner_first_name',
                        'label' => 'First name',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'owner_last_name',
                        'label' => 'Last name',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'owner_email',
                        'label' => 'Work email',
                        'type' => 'email',
                        'required' => true,
                    ],
                    [
                        'key' => 'owner_whatsapp',
                        'label' => 'WhatsApp number',
                        'type' => 'tel',
                        'required' => true,
                        'help' => 'Include country code, e.g. +447700900123',
                    ],
                    [
                        'key' => 'contact_email',
                        'label' => 'Public contact email',
                        'type' => 'email',
                        'required' => true,
                        'help' => 'Shown to clients on booking and join pages.',
                    ],
                ],
            ],
            [
                'id' => 'location',
                'title' => 'Primary location',
                'description' => 'Your first salon floor — you can add more after activation.',
                'fields' => [
                    [
                        'key' => 'location_name',
                        'label' => 'Location name',
                        'type' => 'text',
                        'required' => true,
                        'placeholder' => 'e.g. High Street',
                    ],
                    [
                        'key' => 'postcode',
                        'label' => 'Postcode',
                        'type' => 'text',
                        'required' => true,
                        'placeholder' => 'e.g. E1 1AA',
                        'help' => 'Enter a UK postcode to auto-suggest the full address.',
                    ],
                    [
                        'key' => 'address_line1',
                        'label' => 'Address line 1',
                        'type' => 'text',
                        'required' => true,
                        'placeholder' => 'Street address',
                    ],
                    [
                        'key' => 'city',
                        'label' => 'City',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'country',
                        'label' => 'Country code',
                        'type' => 'text',
                        'required' => true,
                        'placeholder' => 'GB',
                    ],
                    [
                        'key' => 'opening_time',
                        'label' => 'Opening time',
                        'type' => 'time',
                        'required' => true,
                        'help' => 'Used on your booking page for live open / closing status.',
                    ],
                    [
                        'key' => 'closing_time',
                        'label' => 'Closing time',
                        'type' => 'time',
                        'required' => true,
                        'help' => 'Status switches to “We’re Closing” 30 minutes before this time.',
                    ],
                ],
            ],
            [
                'id' => 'plan',
                'title' => 'Choose a plan',
                'description' => 'Every new salon starts on Basic with a 30-day trial. Pro and Diamond unlock after the trial unless a platform admin activates them early.',
                'fields' => [
                    [
                        'key' => 'desired_plan_slug',
                        'label' => 'Interested plan',
                        'type' => 'plan_picker',
                        'required' => true,
                    ],
                ],
            ],
        ];
    }

    public function ensureDefaultActive(): PlatformSignupFormDefinition
    {
        $active = PlatformSignupFormDefinition::query()->where('is_active', true)->first();
        if ($active === null) {
            return PlatformSignupFormDefinition::query()->create([
                'name' => 'Default tenant signup',
                'slug' => 'default',
                'description' => 'Multi-step wizard for new salon tenants.',
                'steps' => self::defaultSteps(),
                'is_active' => true,
                'version' => 1,
            ]);
        }

        $this->ensureServicesStepPresent($active);
        $this->ensureCanonicalStepOrder($active);
        $this->ensureLocationPostcodeFirst($active);

        return $active->fresh();
    }

    public function activePublicPayload(): array
    {
        $form = $this->ensureDefaultActive();
        $plans = SubscriptionPlan::query()
            ->whereIn('slug', ['basic', 'pro', 'diamond'])
            ->where('is_active', true)
            ->orderByRaw("CASE slug WHEN 'basic' THEN 1 WHEN 'pro' THEN 2 WHEN 'diamond' THEN 3 ELSE 9 END")
            ->get()
            ->map(fn (SubscriptionPlan $plan) => [
                'slug' => $plan->slug,
                'name' => $plan->name,
                'description' => $plan->description,
                'display_price_cents' => $plan->display_price_cents,
                'features' => $plan->features,
                'limits' => $plan->limits,
                'is_default' => $plan->slug === 'basic',
                'locked_until_trial_end' => $plan->slug !== 'basic',
            ])
            ->values()
            ->all();

        return [
            'id' => $form->id,
            'name' => $form->name,
            'slug' => $form->slug,
            'version' => $form->version,
            'steps' => $form->steps,
            'plans' => $plans,
            'service_catalogue' => SignupServiceCatalogue::defaults(),
            'trial_days' => 30,
            'default_plan_slug' => 'basic',
            'basic_max_services' => SignupServiceCatalogue::BASIC_MAX_SERVICES,
        ];
    }

    private function ensureServicesStepPresent(PlatformSignupFormDefinition $form): void
    {
        $steps = $form->steps ?? [];
        $hasServices = collect($steps)->contains(
            fn ($step) => ($step['id'] ?? null) === 'services'
                || collect($step['fields'] ?? [])->contains(fn ($f) => ($f['type'] ?? null) === 'service_catalogue'),
        );

        if ($hasServices) {
            return;
        }

        $servicesStep = SignupServiceCatalogue::servicesStep();
        $insertAt = 1;
        foreach ($steps as $index => $step) {
            if (($step['id'] ?? null) === 'business') {
                $insertAt = $index + 1;
                break;
            }
        }
        array_splice($steps, $insertAt, 0, [$servicesStep]);
        $form->steps = $steps;
        $form->version = (int) $form->version + 1;
        $form->save();
    }

    /**
     * Canonical wizard order: business → services → owner → location → plan.
     */
    private function ensureCanonicalStepOrder(PlatformSignupFormDefinition $form): void
    {
        $steps = $form->steps ?? [];
        if ($steps === []) {
            return;
        }

        $byId = [];
        foreach ($steps as $step) {
            $id = $step['id'] ?? null;
            if ($id !== null) {
                $byId[$id] = $step;
            }
        }

        $order = ['business', 'services', 'owner', 'location', 'plan'];
        $reordered = [];
        foreach ($order as $id) {
            if (isset($byId[$id])) {
                $reordered[] = $byId[$id];
                unset($byId[$id]);
            }
        }
        foreach ($byId as $remaining) {
            $reordered[] = $remaining;
        }

        $oldIds = array_values(array_map(fn ($s) => $s['id'] ?? null, $steps));
        $newIds = array_values(array_map(fn ($s) => $s['id'] ?? null, $reordered));
        if ($oldIds === $newIds) {
            return;
        }

        $form->steps = $reordered;
        $form->version = (int) $form->version + 1;
        $form->save();
    }

    private function ensureLocationPostcodeFirst(PlatformSignupFormDefinition $form): void
    {
        $steps = $form->steps ?? [];
        $changed = false;

        foreach ($steps as &$step) {
            if (($step['id'] ?? null) !== 'location') {
                continue;
            }
            $fields = $step['fields'] ?? [];
            $byKey = [];
            foreach ($fields as $field) {
                if (isset($field['key'])) {
                    $byKey[$field['key']] = $field;
                }
            }
            if (! isset($byKey['postcode'])) {
                // still inject opening hours fields below when missing
            } else {
                $byKey['postcode']['placeholder'] = $byKey['postcode']['placeholder'] ?? 'e.g. E1 1AA';
                $byKey['postcode']['help'] = 'Enter a UK postcode to auto-suggest the full address.';
            }

            if (! isset($byKey['opening_time'])) {
                $byKey['opening_time'] = [
                    'key' => 'opening_time',
                    'label' => 'Opening time',
                    'type' => 'time',
                    'required' => true,
                    'help' => 'Used on your booking page for live open / closing status.',
                ];
                $changed = true;
            }
            if (! isset($byKey['closing_time'])) {
                $byKey['closing_time'] = [
                    'key' => 'closing_time',
                    'label' => 'Closing time',
                    'type' => 'time',
                    'required' => true,
                    'help' => 'Status switches to “We’re Closing” 30 minutes before this time.',
                ];
                $changed = true;
            }

            $order = ['location_name', 'postcode', 'address_line1', 'city', 'country', 'opening_time', 'closing_time'];
            $reordered = [];
            foreach ($order as $key) {
                if (isset($byKey[$key])) {
                    $reordered[] = $byKey[$key];
                    unset($byKey[$key]);
                }
            }
            foreach ($byKey as $remaining) {
                $reordered[] = $remaining;
            }

            if (json_encode($fields) !== json_encode($reordered)) {
                $step['fields'] = $reordered;
                $changed = true;
            }
        }
        unset($step);

        if (! $changed) {
            return;
        }

        $form->steps = $steps;
        $form->version = (int) $form->version + 1;
        $form->save();
    }

    public function list(): Collection
    {
        return PlatformSignupFormDefinition::query()->orderByDesc('updated_at')->get();
    }

    public function create(array $data): PlatformSignupFormDefinition
    {
        $slug = Str::slug($data['slug'] ?? $data['name']);
        if (PlatformSignupFormDefinition::query()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['slug' => ['Slug already in use.']]);
        }

        $form = PlatformSignupFormDefinition::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'steps' => $data['steps'] ?? self::defaultSteps(),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'version' => 1,
        ]);

        if ($form->is_active) {
            $this->deactivateOthers($form->id);
        }

        $this->audit->log('platform.signup_form.created', $form, null, $form->toArray());

        return $form;
    }

    public function update(PlatformSignupFormDefinition $form, array $data): PlatformSignupFormDefinition
    {
        $old = $form->toArray();

        if (isset($data['slug'])) {
            $slug = Str::slug($data['slug']);
            $clash = PlatformSignupFormDefinition::query()
                ->where('slug', $slug)
                ->where('id', '!=', $form->id)
                ->exists();
            if ($clash) {
                throw ValidationException::withMessages(['slug' => ['Slug already in use.']]);
            }
            $form->slug = $slug;
        }

        if (array_key_exists('name', $data)) {
            $form->name = $data['name'];
        }
        if (array_key_exists('description', $data)) {
            $form->description = $data['description'];
        }
        if (array_key_exists('steps', $data)) {
            $form->steps = $data['steps'];
            $form->version = (int) $form->version + 1;
        }
        if (array_key_exists('is_active', $data)) {
            $form->is_active = (bool) $data['is_active'];
        }

        $form->save();

        if ($form->is_active) {
            $this->deactivateOthers($form->id);
        }

        $this->audit->log('platform.signup_form.updated', $form, $old, $form->toArray());

        return $form->fresh();
    }

    public function delete(PlatformSignupFormDefinition $form): void
    {
        if ($form->is_active) {
            throw ValidationException::withMessages([
                'form' => ['Deactivate the form before deleting, or activate another form first.'],
            ]);
        }

        $snapshot = $form->toArray();
        $form->delete();
        $this->audit->log('platform.signup_form.deleted', null, $snapshot, null);
    }

    private function deactivateOthers(string $keepId): void
    {
        PlatformSignupFormDefinition::query()
            ->where('id', '!=', $keepId)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }
}
