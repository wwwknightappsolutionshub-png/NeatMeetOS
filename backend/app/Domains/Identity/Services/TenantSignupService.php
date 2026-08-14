<?php

namespace App\Domains\Identity\Services;

use App\Domains\Booking\Models\BookableService;
use App\Domains\Identity\Models\AuthActionToken;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\SubscriptionPlan;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\TenantSubscription;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\Workspace;
use App\Domains\Identity\Support\SignupServiceCatalogue;
use App\Domains\Lookbook\Services\LookbookSeedService;
use App\Jobs\SendTenantSignupWelcomeWhatsAppJob;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class TenantSignupService
{
    public const TRIAL_DAYS = 30;

    public const TIER_SLUGS = ['basic', 'pro', 'diamond'];

    public function __construct(
        private readonly AuthActionTokenService $tokens,
        private readonly AuthMailService $mail,
        private readonly AuditLogger $audit,
        private readonly TenantContext $tenantContext,
        private readonly PlatformNotificationService $platformNotifications,
        private readonly PlatformReferralProgramService $platformReferrals,
        private readonly LookbookSeedService $lookbookSeed,
    ) {}

    /**
     * Marketing lead capture: provisional user + temp password email (no tenant yet).
     *
     * @param  array{name: string, email: string, referral_code?: string|null, website?: string|null, whatsapp?: string|null}  $data
     * @return array{status: string, message: string, login_url: string, temporary_password?: string|null}
     */
    public function captureLead(array $data): array
    {
        // Honeypot — bots fill hidden trap fields; pretend success. Do not use
        // autofill tokens like "website" (password managers fill those).
        $honeypot = trim((string) ($data['hp_trap'] ?? $data['website'] ?? ''));
        if ($honeypot !== '') {
            return [
                'status' => 'created',
                'message' => 'Check your email for your temporary password and login link.',
                'login_url' => $this->loginUrlWithEmail((string) ($data['email'] ?? '')),
            ];
        }

        $name = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $referralCode = strtoupper(trim((string) ($data['referral_code'] ?? '')));
        $whatsapp = $this->normalizeOptionalWhatsapp($data['whatsapp'] ?? null);

        if ($name === '' || strlen($name) < 2) {
            throw ValidationException::withMessages(['name' => ['Please enter your name.']]);
        }
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => ['A valid email is required.']]);
        }

        $loginUrl = $this->loginUrlWithEmail($email);
        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null && ! $existing->needsWorkspace()) {
            return [
                'status' => 'existing',
                'message' => 'An account with this email already exists. Sign in to continue.',
                'login_url' => $loginUrl,
            ];
        }

        $plainPassword = Str::password(12, symbols: false);

        if ($existing !== null && $existing->needsWorkspace()) {
            $meta = is_array($existing->signup_meta) ? $existing->signup_meta : [];
            if ($referralCode !== '') {
                $meta['referral_code'] = $referralCode;
            }
            if ($whatsapp !== null) {
                $meta['whatsapp'] = $whatsapp;
            }
            $existing->forceFill([
                'name' => $name,
                'password' => $plainPassword,
                'email_verified_at' => $existing->email_verified_at ?? now(),
                'signup_meta' => $meta,
            ])->save();

            $mailSent = $this->sendWelcomeTrialSafely($existing, $plainPassword);
            $this->audit->log('tenant.signup.lead_resent', $existing, null, [
                'email' => $email,
                'has_referral' => $referralCode !== '',
                'mail_sent' => $mailSent,
                'whatsapp_welcome_queued' => $whatsapp !== null,
            ], $existing);

            return [
                'status' => 'resent',
                'message' => $mailSent
                    ? 'Check your inbox / spam box for your temporary login details.'
                    : 'Your trial account is ready. Use the temporary password shown to sign in.',
                'login_url' => $loginUrl,
                'temporary_password' => $mailSent ? null : $plainPassword,
            ];
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $plainPassword,
            'email_verified_at' => now(),
            'workspace_status' => User::WORKSPACE_PROVISIONAL,
            'signup_meta' => array_filter([
                'referral_code' => $referralCode !== '' ? $referralCode : null,
                'whatsapp' => $whatsapp,
                'captured_at' => now()->toIso8601String(),
                'source' => 'marketing_landing',
            ]),
        ]);

        $mailSent = $this->sendWelcomeTrialSafely($user, $plainPassword);
        $this->audit->log('tenant.signup.lead_captured', $user, null, [
            'email' => $email,
            'has_referral' => $referralCode !== '',
            'mail_sent' => $mailSent,
            'whatsapp_welcome_queued' => $whatsapp !== null,
        ], $user);

        return [
            'status' => 'created',
            'message' => $mailSent
                ? 'Check your inbox / spam box for your temporary login details.'
                : 'Your trial account is ready. Use the temporary password shown to sign in.',
            'login_url' => $loginUrl,
            // Only returned when SMTP fails so the funnel is not blocked in production.
            'temporary_password' => $mailSent ? null : $plainPassword,
        ];
    }

    private function sendWelcomeTrialSafely(User $user, string $plainPassword): bool
    {
        try {
            $this->mail->sendWelcomeTrial($user, $plainPassword);
            $this->queueSignupWelcomeWhatsAppTrial($user, $plainPassword);

            return true;
        } catch (\Throwable $e) {
            report($e);
            // Still attempt WhatsApp even if mail fails.
            $this->queueSignupWelcomeWhatsAppTrial($user, $plainPassword);

            return false;
        }
    }

    private function queueSignupWelcomeWhatsAppTrial(User $user, string $plainPassword): void
    {
        try {
            SendTenantSignupWelcomeWhatsAppJob::dispatchTrial($user, $plainPassword);
        } catch (Throwable $e) {
            Log::error('signup.welcome_whatsapp_dispatch_failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function normalizeOptionalWhatsapp(mixed $value): ?string
    {
        $phone = preg_replace('/\s+/', '', trim((string) ($value ?? ''))) ?? '';
        if ($phone === '') {
            return null;
        }
        if (! str_starts_with($phone, '+') || strlen($phone) < 8) {
            throw ValidationException::withMessages([
                'whatsapp' => ['WhatsApp must be E.164, e.g. +447700900123.'],
            ]);
        }

        return $phone;
    }

    /**
     * Finish workspace provisioning for a provisional (lead) user who is already authenticated.
     * Replaces the temporary unlock password with the owner's permanent password.
     *
     * @param  array<string, mixed>  $answers
     * @return array{tenant: Tenant, user: User, token: string}
     */
    public function completeWorkspace(User $user, string $permanentPassword, array $answers): array
    {
        if (! $user->needsWorkspace()) {
            throw ValidationException::withMessages([
                'email' => ['Your workspace is already set up. Sign in to your dashboard.'],
            ]);
        }

        // Prefer a plain exists() check (no relation load) before provisioning.
        $alreadyLinked = TeamMember::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->whereNotNull('tenant_id')
            ->exists();
        if ($alreadyLinked) {
            throw ValidationException::withMessages([
                'email' => ['Your workspace is already set up. Sign in to your dashboard.'],
            ]);
        }

        $meta = is_array($user->signup_meta) ? $user->signup_meta : [];
        if (empty($answers['referral_code']) && ! empty($meta['referral_code'])) {
            $answers['referral_code'] = $meta['referral_code'];
        }

        $answers['owner_email'] = $user->email;

        $nameParts = preg_split('/\s+/', trim($user->name), 2) ?: [];
        if (empty($answers['owner_first_name'])) {
            $answers['owner_first_name'] = $nameParts[0] ?? 'Owner';
        }
        if (empty($answers['owner_last_name'])) {
            $answers['owner_last_name'] = $nameParts[1] ?? 'Account';
        }

        $payload = $this->normalizeAnswers($answers);
        if ($payload['owner_email'] !== strtolower($user->email)) {
            throw ValidationException::withMessages([
                'owner_email' => ['Email is locked to your trial account.'],
            ]);
        }

        if (Tenant::query()->where('slug', $payload['slug'])->exists()) {
            throw ValidationException::withMessages([
                'slug' => ['This booking URL slug is already taken. Choose another.'],
            ]);
        }

        $basicPlan = SubscriptionPlan::query()->where('slug', 'basic')->where('is_active', true)->first();
        if ($basicPlan === null) {
            throw ValidationException::withMessages([
                'plan' => ['Basic plan is not configured. Contact support.'],
            ]);
        }

        $desiredSlug = $payload['desired_plan_slug'];
        if (! in_array($desiredSlug, self::TIER_SLUGS, true)) {
            throw ValidationException::withMessages([
                'desired_plan_slug' => ['Select Basic, Pro, or Diamond.'],
            ]);
        }

        try {
            /** @var Tenant $tenant */
            $tenant = DB::transaction(function () use ($payload, $basicPlan, $desiredSlug, $user, $permanentPassword) {
                $tenant = Tenant::query()->create([
                    'name' => $payload['business_name'],
                    'trading_name' => $payload['trading_name'] ?: $payload['business_name'],
                    'slug' => $payload['slug'],
                    'status' => 'active',
                    'activated_at' => now(),
                    'business_type' => $payload['business_type'],
                    'timezone' => $payload['timezone'],
                    'contact_email' => $payload['contact_email'],
                    'contact_phone' => $payload['owner_whatsapp'],
                    'owner_whatsapp' => $payload['owner_whatsapp'],
                    'subscription_plan_id' => $basicPlan->id,
                    'settings' => [
                        'branding' => array_merge(Tenant::BRANDING_DEFAULTS, [
                            'brand_display_name' => $payload['business_name'],
                            'primary_color' => '#2f5a45',
                            'secondary_color' => '#fafaf9',
                            'support_email' => $payload['contact_email'],
                            'support_phone' => $payload['owner_whatsapp'],
                        ]),
                        'signup' => [
                            'desired_plan_slug' => $desiredSlug,
                            'registered_at' => now()->toIso8601String(),
                            'source' => 'marketing_lead',
                        ],
                    ],
                ]);

                TenantSubscription::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id,
                    'subscription_plan_id' => $basicPlan->id,
                    'desired_plan_slug' => $desiredSlug,
                    'tier_unlocked' => false,
                    'status' => TenantSubscription::STATUS_TRIAL,
                    'billing_interval' => SubscriptionPlan::INTERVAL_MONTHLY,
                    'trial_ends_at' => now()->addDays(self::TRIAL_DAYS),
                    'current_period_start' => now(),
                    'current_period_end' => now()->addDays(self::TRIAL_DAYS),
                ]);

                $location = Location::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id,
                    'name' => $payload['location_name'],
                    'slug' => Str::slug($payload['location_name']).'-'.Str::lower(Str::random(4)),
                    'timezone' => $payload['timezone'],
                    'address' => [
                        'line1' => $payload['address_line1'],
                        'city' => $payload['city'],
                        'postcode' => $payload['postcode'],
                        'country' => strtoupper($payload['country']),
                    ],
                    'contact_phone' => $payload['owner_whatsapp'],
                    'opening_hours' => $this->openingHoursFromSignup(
                        $payload['opening_time'],
                        $payload['closing_time'],
                    ),
                    'is_active' => true,
                ]);

                $workspace = Workspace::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id,
                    'location_id' => $location->id,
                    'name' => 'Chair 1',
                    'code' => 'C1',
                    'workspace_type' => Workspace::TYPE_CHAIR,
                    'is_active' => true,
                ]);

                $ownerRole = Role::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Owner',
                    'slug' => 'owner',
                    'is_system' => true,
                    'is_active' => true,
                ]);
                $ownerRole->permissions()->sync($this->ownerPermissionIds());

                $teamMember = TeamMember::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'first_name' => $payload['owner_first_name'],
                    'last_name' => $payload['owner_last_name'],
                    'employment_type' => TeamMember::EMPLOYMENT_OWNER,
                    'display_name' => trim($payload['owner_first_name'].' '.$payload['owner_last_name']),
                    'phone' => $payload['owner_whatsapp'],
                    'primary_location_id' => $location->id,
                    'is_active' => true,
                ]);
                $teamMember->roles()->attach($ownerRole->id);
                $teamMember->workspaces()->attach($workspace->id);

                // Permanent password replaces the temporary unlock password.
                $user->forceFill([
                    'name' => trim($payload['owner_first_name'].' '.$payload['owner_last_name']),
                    'password' => $permanentPassword,
                    'workspace_status' => User::WORKSPACE_COMPLETE,
                    'signup_meta' => array_merge(
                        is_array($user->signup_meta) ? $user->signup_meta : [],
                        [
                            'completed_at' => now()->toIso8601String(),
                            'temporary_password_invalidated_at' => now()->toIso8601String(),
                        ],
                    ),
                ])->save();

                $this->tenantContext->set($tenant);
                $this->createSignupServices($tenant->id, $payload['services']);

                $this->audit->log('tenant.signup.workspace_completed', $tenant, null, [
                    'slug' => $tenant->slug,
                    'desired_plan_slug' => $desiredSlug,
                    'owner_email' => $user->email,
                    'services_count' => count($payload['services']),
                    'source' => 'marketing_lead',
                    'permanent_password_set' => true,
                ], $user);

                return $tenant;
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages([
                'slug' => ['This booking URL slug is already taken. Choose another.'],
            ]);
        }

        // Non-critical: lookbook seed / referrals / admin mail must not block workspace open.
        $this->safeSeedLookbook($tenant);
        $this->safeRunSignupSideEffects($tenant, $user->fresh() ?? $user, $payload['referral_code']);

        // Drop tokens issued with the temporary password; issue a fresh session token.
        $user->tokens()->delete();
        $sanctum = $user->createToken('neatmeet-os-web')->plainTextToken;

        return [
            'tenant' => $tenant,
            'user' => $user->fresh(),
            'token' => $sanctum,
        ];
    }

    private function loginUrlWithEmail(string $email): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');
        $email = strtolower(trim($email));

        return $email !== ''
            ? $base.'/login?tab=signup&email='.urlencode($email)
            : $base.'/login?tab=signup';
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array{tenant: Tenant, user: User, activation_sent: bool}
     */
    public function register(array $answers): array
    {
        $payload = $this->normalizeAnswers($answers);
        $this->assertUniqueEmailAndSlug($payload['owner_email'], $payload['slug']);

        $basicPlan = SubscriptionPlan::query()->where('slug', 'basic')->where('is_active', true)->first();
        if ($basicPlan === null) {
            throw ValidationException::withMessages([
                'plan' => ['Basic plan is not configured. Contact support.'],
            ]);
        }

        $desiredSlug = $payload['desired_plan_slug'];
        if (! in_array($desiredSlug, self::TIER_SLUGS, true)) {
            throw ValidationException::withMessages([
                'desired_plan_slug' => ['Select Basic, Pro, or Diamond.'],
            ]);
        }

        /** @var array{tenant: Tenant, user: User, plainToken: string} $created */
        $created = DB::transaction(function () use ($payload, $basicPlan, $desiredSlug) {
            $tenant = Tenant::query()->create([
                'name' => $payload['business_name'],
                'trading_name' => $payload['trading_name'] ?: $payload['business_name'],
                'slug' => $payload['slug'],
                'status' => 'pending_activation',
                'business_type' => $payload['business_type'],
                'timezone' => $payload['timezone'],
                'contact_email' => $payload['contact_email'],
                'contact_phone' => $payload['owner_whatsapp'],
                'owner_whatsapp' => $payload['owner_whatsapp'],
                'subscription_plan_id' => $basicPlan->id,
                'settings' => [
                    'branding' => array_merge(Tenant::BRANDING_DEFAULTS, [
                        'brand_display_name' => $payload['business_name'],
                        'primary_color' => '#2f5a45',
                        'secondary_color' => '#fafaf9',
                        'support_email' => $payload['contact_email'],
                        'support_phone' => $payload['owner_whatsapp'],
                    ]),
                    'signup' => [
                        'desired_plan_slug' => $desiredSlug,
                        'registered_at' => now()->toIso8601String(),
                    ],
                ],
            ]);

            TenantSubscription::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'subscription_plan_id' => $basicPlan->id,
                'desired_plan_slug' => $desiredSlug,
                'tier_unlocked' => false,
                'status' => TenantSubscription::STATUS_TRIAL,
                'billing_interval' => SubscriptionPlan::INTERVAL_MONTHLY,
                'trial_ends_at' => now()->addDays(self::TRIAL_DAYS),
                'current_period_start' => now(),
                'current_period_end' => now()->addDays(self::TRIAL_DAYS),
            ]);

            $location = Location::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'name' => $payload['location_name'],
                'slug' => Str::slug($payload['location_name']).'-'.Str::lower(Str::random(4)),
                'timezone' => $payload['timezone'],
                'address' => [
                    'line1' => $payload['address_line1'],
                    'city' => $payload['city'],
                    'postcode' => $payload['postcode'],
                    'country' => strtoupper($payload['country']),
                ],
                'contact_phone' => $payload['owner_whatsapp'],
                'opening_hours' => $this->openingHoursFromSignup(
                    $payload['opening_time'],
                    $payload['closing_time'],
                ),
                'is_active' => true,
            ]);

            $workspace = Workspace::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'location_id' => $location->id,
                'name' => 'Chair 1',
                'code' => 'C1',
                'workspace_type' => Workspace::TYPE_CHAIR,
                'is_active' => true,
            ]);

            $ownerRole = Role::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'name' => 'Owner',
                'slug' => 'owner',
                'is_system' => true,
                'is_active' => true,
            ]);
            $ownerRole->permissions()->sync($this->ownerPermissionIds());

            $user = new User();
            $user->forceFill([
                'name' => trim($payload['owner_first_name'].' '.$payload['owner_last_name']),
                'email' => $payload['owner_email'],
                'password' => Str::random(40),
                'workspace_status' => User::WORKSPACE_COMPLETE,
            ])->save();

            $teamMember = TeamMember::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'first_name' => $payload['owner_first_name'],
                'last_name' => $payload['owner_last_name'],
                'employment_type' => TeamMember::EMPLOYMENT_OWNER,
                'display_name' => trim($payload['owner_first_name'].' '.$payload['owner_last_name']),
                'phone' => $payload['owner_whatsapp'],
                'primary_location_id' => $location->id,
                'is_active' => true,
            ]);
            $teamMember->roles()->attach($ownerRole->id);
            $teamMember->workspaces()->attach($workspace->id);

            $this->tenantContext->set($tenant);
            $this->createSignupServices($tenant->id, $payload['services']);

            $this->audit->log('tenant.signup.registered', $tenant, null, [
                'slug' => $tenant->slug,
                'desired_plan_slug' => $desiredSlug,
                'owner_email' => $user->email,
                'services_count' => count($payload['services']),
            ], $user);

            $plainToken = $this->tokens->issue(
                $user,
                AuthActionToken::PURPOSE_ACTIVATION,
                $tenant->id,
                60 * 48,
            );

            return compact('tenant', 'user', 'plainToken');
        });

        $this->safeSeedLookbook($created['tenant']);
        $this->safeRunSignupSideEffects(
            $created['tenant'],
            $created['user'],
            $payload['referral_code'],
        );

        try {
            $this->mail->sendTenantActivation($created['user'], $created['plainToken'], $created['tenant']->name);
        } catch (Throwable $e) {
            Log::error('signup.activation_mail_failed', [
                'tenant_id' => $created['tenant']->id,
                'user_id' => $created['user']->id,
                'message' => $e->getMessage(),
            ]);
        }

        try {
            SendTenantSignupWelcomeWhatsAppJob::dispatchActivation(
                $created['user'],
                $created['tenant'],
                $created['plainToken'],
            );
        } catch (Throwable $e) {
            Log::error('signup.activation_whatsapp_dispatch_failed', [
                'tenant_id' => $created['tenant']->id,
                'user_id' => $created['user']->id,
                'message' => $e->getMessage(),
            ]);
        }

        return [
            'tenant' => $created['tenant'],
            'user' => $created['user'],
            'activation_sent' => true,
        ];
    }

    /**
     * @return array{token: string, user: User, tenant: ?Tenant}
     */
    public function activate(string $plainToken, string $password): array
    {
        $action = $this->tokens->consume($plainToken, AuthActionToken::PURPOSE_ACTIVATION);
        $user = User::query()->findOrFail($action->user_id);
        $tenant = Tenant::query()->find($action->tenant_id);

        if ($tenant === null) {
            throw ValidationException::withMessages(['token' => ['Tenant not found for this activation link.']]);
        }

        if ($tenant->status !== 'pending_activation' && $tenant->status !== 'trial') {
            // Allow re-activation path only while pending; if already active, just set password.
        }

        $user->forceFill([
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ])->save();

        if ($tenant->status === 'pending_activation') {
            $tenant->forceFill([
                'status' => 'active',
                'activated_at' => now(),
            ])->save();
            $this->platformReferrals->handleTenantActivated($tenant);
        } elseif ($tenant->activated_at === null) {
            $tenant->forceFill(['activated_at' => now()])->save();
            $this->platformReferrals->handleTenantActivated($tenant);
        }

        $this->tenantContext->set($tenant);
        $this->safeSeedLookbook($tenant);
        $this->audit->log('tenant.signup.activated', $tenant, null, [
            'user_id' => $user->id,
        ], $user);

        $sanctum = $user->createToken('neatmeet-os-web')->plainTextToken;

        return [
            'token' => $sanctum,
            'user' => $user,
            'tenant' => $tenant,
        ];
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    private function normalizeAnswers(array $answers): array
    {
        $businessName = trim((string) ($answers['business_name'] ?? ''));
        $ownerEmail = strtolower(trim((string) ($answers['owner_email'] ?? '')));
        $whatsapp = trim((string) ($answers['owner_whatsapp'] ?? ''));

        if ($businessName === '') {
            throw ValidationException::withMessages(['business_name' => ['Salon name is required.']]);
        }
        if ($ownerEmail === '' || ! filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['owner_email' => ['A valid owner email is required.']]);
        }
        if ($whatsapp === '' || strlen($whatsapp) < 8) {
            throw ValidationException::withMessages(['owner_whatsapp' => ['WhatsApp number is required.']]);
        }

        $slugInput = trim((string) ($answers['slug'] ?? ''));
        $slug = $slugInput !== '' ? Str::slug($slugInput) : Str::slug($businessName);
        if ($slug === '') {
            $slug = 'salon-'.Str::lower(Str::random(6));
        }

        $contactEmail = trim((string) ($answers['contact_email'] ?? ''));
        if ($contactEmail === '') {
            $contactEmail = $ownerEmail;
        }

        $opening = $this->normalizeClock((string) ($answers['opening_time'] ?? '09:00'));
        $closing = $this->normalizeClock((string) ($answers['closing_time'] ?? '18:00'));
        if ($opening >= $closing) {
            throw ValidationException::withMessages([
                'closing_time' => ['Closing time must be after opening time.'],
            ]);
        }

        return [
            'business_name' => $businessName,
            'trading_name' => trim((string) ($answers['trading_name'] ?? '')),
            'slug' => $slug,
            'business_type' => trim((string) ($answers['business_type'] ?? 'boutique')) ?: 'boutique',
            'timezone' => trim((string) ($answers['timezone'] ?? 'Europe/London')) ?: 'Europe/London',
            'owner_first_name' => trim((string) ($answers['owner_first_name'] ?? '')),
            'owner_last_name' => trim((string) ($answers['owner_last_name'] ?? '')),
            'owner_email' => $ownerEmail,
            'owner_whatsapp' => $whatsapp,
            'contact_email' => $contactEmail,
            'location_name' => trim((string) ($answers['location_name'] ?? 'Main location')) ?: 'Main location',
            'address_line1' => trim((string) ($answers['address_line1'] ?? '')),
            'city' => trim((string) ($answers['city'] ?? '')),
            'postcode' => trim((string) ($answers['postcode'] ?? '')),
            'country' => trim((string) ($answers['country'] ?? 'GB')) ?: 'GB',
            'opening_time' => $opening,
            'closing_time' => $closing,
            'desired_plan_slug' => strtolower(trim((string) ($answers['desired_plan_slug'] ?? 'basic'))) ?: 'basic',
            'services' => $this->normalizeServices($answers['services'] ?? []),
            'referral_code' => strtoupper(trim((string) ($answers['referral_code'] ?? ''))),
        ];
    }

    private function normalizeClock(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $raw, $m)) {
            $hh = min(23, (int) $m[1]);
            $mm = min(59, (int) $m[2]);

            return sprintf('%02d:%02d', $hh, $mm);
        }
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (strlen($digits) >= 4) {
            $hh = min(23, (int) substr($digits, 0, 2));
            $mm = min(59, (int) substr($digits, 2, 2));

            return sprintf('%02d:%02d', $hh, $mm);
        }

        return '09:00';
    }

    /**
     * @param  mixed  $raw
     * @return list<array{name: string, category: ?string, description: ?string, image_url: ?string, duration_minutes: int, base_price_cents: int}>
     */
    private function normalizeServices(mixed $raw): array
    {
        if (! is_array($raw) || $raw === []) {
            throw ValidationException::withMessages([
                'services' => ['Select at least one service to offer.'],
            ]);
        }

        $services = [];
        foreach ($raw as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                throw ValidationException::withMessages([
                    "services.$index.name" => ['Service name is required.'],
                ]);
            }
            $duration = (int) ($row['duration_minutes'] ?? 0);
            if ($duration < 5 || $duration > 480) {
                throw ValidationException::withMessages([
                    "services.$index.duration_minutes" => ['Minimum completion time must be between 5 and 480 minutes.'],
                ]);
            }
            $price = (int) ($row['base_price_cents'] ?? 0);
            if ($price < 0) {
                throw ValidationException::withMessages([
                    "services.$index.base_price_cents" => ['Price cannot be negative.'],
                ]);
            }

            $imageUrl = trim((string) ($row['image_url'] ?? ''));
            $services[] = [
                'name' => $name,
                'category' => trim((string) ($row['category'] ?? '')) ?: null,
                'description' => trim((string) ($row['description'] ?? '')) ?: null,
                'image_url' => $imageUrl !== '' ? $imageUrl : null,
                'duration_minutes' => $duration,
                'base_price_cents' => $price,
            ];
        }

        if ($services === []) {
            throw ValidationException::withMessages([
                'services' => ['Select at least one service to offer.'],
            ]);
        }

        if (count($services) > SignupServiceCatalogue::BASIC_MAX_SERVICES) {
            throw ValidationException::withMessages([
                'services' => ['Upgrade your plan to unlock more. Basic includes up to '.SignupServiceCatalogue::BASIC_MAX_SERVICES.' services.'],
            ]);
        }

        return $services;
    }

    /**
     * @param  list<array{name: string, category: ?string, description: ?string, image_url: ?string, duration_minutes: int, base_price_cents: int}>  $services
     */
    private function createSignupServices(string $tenantId, array $services): void
    {
        foreach (array_values($services) as $index => $service) {
            $base = $service['base_price_cents'];
            BookableService::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'name' => $service['name'],
                'category' => $service['category'],
                'description' => $service['description'],
                'image_url' => $service['image_url'],
                'duration_minutes' => $service['duration_minutes'],
                'base_price_cents' => $base,
                'membership_price_cents' => $base > 0 ? (int) round($base * 0.85) : 0,
                'loyalty_price_cents' => $base > 0 ? (int) round($base * 0.90) : 0,
                'is_active' => true,
                'is_bookable_online' => true,
                'display_order' => $index + 1,
                'deposit_required' => false,
            ]);
        }
    }

    private function assertUniqueEmailAndSlug(string $email, string $slug): void
    {
        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'owner_email' => ['An account with this email already exists. Sign in or use magic login.'],
            ]);
        }

        if (Tenant::query()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([
                'slug' => ['This booking URL slug is already taken. Choose another.'],
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function ownerPermissionIds(): array
    {
        $ids = [
            'identity.view',
            'identity.manage',
            'identity.access.manage',
            'booking.view',
            'booking.manage',
            'crm.view',
            'crm.manage',
            'staff.view',
            'staff.manage',
            'payments.view',
            'payments.manage',
            'payments.refund',
            'payments.reporting.view',
            'inventory.view',
            'inventory.manage',
            'inventory.adjust',
            'inventory.reporting.view',
            'pos.view',
            'pos.manage',
            'pos.checkout.complete',
            'pos.refund',
            'pos.checkout.reopen',
            'pos.receipt.manage',
            'memberships.view',
            'memberships.manage',
            'memberships.reporting.view',
            'marketing.view',
            'marketing.manage',
            'marketing.dispatch',
            'marketing.reporting.view',
            'notifications.view',
            'notifications.manage',
            'notifications.reporting.view',
            'analytics.view',
            'analytics.reporting.view',
            'analytics.exports.manage',
            'integrations.view',
            'integrations.manage',
            'integrations.reporting.view',
            'ecommerce.view',
            'ecommerce.manage',
            'gallery.view',
            'gallery.manage',
            'lookbook.view',
            'lookbook.manage',
            'next_visit.view',
            'next_visit.manage',
            'ai_hairstyle.view',
            'ai_hairstyle.manage',
        ];

        foreach ($ids as $id) {
            Permission::query()->firstOrCreate(
                ['id' => $id],
                ['name' => $id, 'slug' => $id, 'module' => explode('.', $id)[0]],
            );
        }

        return $ids;
    }

    private function safeSeedLookbook(Tenant $tenant): void
    {
        try {
            $this->lookbookSeed->seedForTenant($tenant);
        } catch (Throwable $e) {
            Log::error('signup.lookbook_seed_failed', [
                'tenant_id' => $tenant->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function safeRunSignupSideEffects(Tenant $tenant, User $user, string $referralCode): void
    {
        try {
            if ($referralCode !== '') {
                $this->platformReferrals->attachOnSignup($tenant, $referralCode);
            }
            $this->platformReferrals->handleTenantActivated($tenant);
        } catch (Throwable $e) {
            Log::error('signup.referral_hooks_failed', [
                'tenant_id' => $tenant->id,
                'message' => $e->getMessage(),
            ]);
        }

        try {
            $this->platformNotifications->notifyTenantSignup($tenant, $user);
        } catch (Throwable $e) {
            Log::error('signup.platform_notify_failed', [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function openingHoursFromSignup(string $open, string $close): array
    {
        $hours = [];
        for ($day = 1; $day <= 6; $day++) {
            $hours[] = [
                'day_of_week' => $day,
                'start_time' => $open,
                'end_time' => $close,
                'is_closed' => false,
            ];
        }
        $hours[] = [
            'day_of_week' => 7,
            'start_time' => null,
            'end_time' => null,
            'is_closed' => true,
        ];

        return $hours;
    }

    private function defaultOpeningHours(): array
    {
        return $this->openingHoursFromSignup('09:00', '18:00');
    }
}
