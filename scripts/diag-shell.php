<?php

/**
 * Production diagnostic: GET /api/v1/shell for a user email (issues a temp token).
 *
 * Usage: php scripts/diag-shell.php beacadmedia@gmail.com
 */

use App\Domains\Crm\Services\MemberPushDispatchService;
use App\Domains\Identity\Http\Controllers\ShellController;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\TenantEntitlementService;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

$backend = dirname(__DIR__).'/backend';
require $backend.'/vendor/autoload.php';
$app = require $backend.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = strtolower(trim($argv[1] ?? ''));
if ($email === '') {
    fwrite(STDERR, "Usage: php diag-shell.php owner@example.com\n");
    exit(1);
}

$user = User::query()->where('email', $email)->first();
if ($user === null) {
    echo "USER_MISSING\n";
    exit(1);
}

$member = $user->resolveActiveTeamMember();
$tenant = $member?->tenant;
$slug = $tenant?->slug;

echo 'user_id='.$user->id.PHP_EOL;
echo 'workspace_status='.($user->workspace_status ?? 'null').PHP_EOL;
echo 'tenant_slug='.($slug ?: 'null').PHP_EOL;
echo 'tenant_status='.($tenant?->status ?? 'null').PHP_EOL;
echo 'subscription_plan_id='.($tenant?->subscription_plan_id ?? 'null').PHP_EOL;
echo 'resolveActiveTeamMember='.($member ? $member->id : 'null').PHP_EOL;

$step = static function (string $label, callable $fn): void {
    try {
        $result = $fn();
        $preview = is_scalar($result) || $result === null
            ? var_export($result, true)
            : (is_array($result) ? 'array('.count($result).')' : get_debug_type($result));
        echo "OK {$label} => {$preview}".PHP_EOL;
    } catch (Throwable $e) {
        echo "FAIL {$label}".PHP_EOL;
        echo '  class='.$e::class.PHP_EOL;
        echo '  message='.$e->getMessage().PHP_EOL;
        echo '  at='.$e->getFile().':'.$e->getLine().PHP_EOL;
    }
};

if ($tenant instanceof Tenant) {
    app(TenantContext::class)->set($tenant);

    $entitlements = app(TenantEntitlementService::class);
    $push = app(MemberPushDispatchService::class);

    $step('client_count', fn () => DB::table('clients')->where('tenant_id', $tenant->id)->count());
    $step('appointment_services_exists', fn () => DB::table('appointment_services')->limit(1)->exists());
    $step('hasBookingWorthAtLeast', function () use ($tenant) {
        return app(\App\Domains\Identity\Services\ProgressiveModuleAccessService::class)
            ->hasBookingWorthAtLeast($tenant, 50000);
    });
    $step('resolveFeatures', fn () => $entitlements->resolveFeatures($tenant));
    $step('lockedModuleHints', fn () => $entitlements->lockedModuleHints($tenant));
    $step('resolveLimits', fn () => $entitlements->resolveLimits($tenant));
    $step('subscription_relation', fn () => $tenant->subscription()->withoutGlobalScopes()->first());
    $step('vapid_public_key', fn () => $push->publicKey());
    $step('ShellController_direct', function () use ($user, $tenant, $entitlements, $push) {
        $request = Request::create('/api/v1/shell', 'GET');
        $request->setUserResolver(static fn () => $user);
        app(TenantContext::class)->set($tenant);
        $request->attributes->set('tenant', $tenant);

        return app(ShellController::class)(
            $request,
            app(TenantContext::class),
            $entitlements,
            $push,
        )->getStatusCode();
    });
}

$token = $user->createToken('diag-shell')->plainTextToken;
$request = Request::create('/api/v1/shell', 'GET');
$request->headers->set('Accept', 'application/json');
$request->headers->set('Authorization', 'Bearer '.$token);
if ($slug) {
    $request->headers->set('X-Tenant-Slug', $slug);
}

Log::listen(static function ($message): void {
    if (isset($message->message) && str_contains((string) $message->message, 'exception')) {
        echo 'LOG '.$message->message.PHP_EOL;
    }
});

try {
    $response = $kernel->handle($request);
    echo 'http_status='.$response->getStatusCode().PHP_EOL;
    echo 'http_body='.substr((string) $response->getContent(), 0, 2000).PHP_EOL;
} catch (Throwable $e) {
    echo 'http_FAIL class='.$e::class.PHP_EOL;
    echo 'http_FAIL message='.$e->getMessage().PHP_EOL;
    echo 'http_FAIL at='.$e->getFile().':'.$e->getLine().PHP_EOL;
}

$logPath = $backend.'/storage/logs/laravel.log';
if (is_file($logPath)) {
    $tail = array_slice(file($logPath) ?: [], -40);
    echo "---- laravel.log tail ----\n";
    echo implode('', $tail);
}

$user->tokens()->where('name', 'diag-shell')->delete();
