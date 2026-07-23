<?php

/**
 * Production diagnostic: run completeWorkspace for a provisional user.
 *
 * Usage (from backend/):
 *   php ../scripts/diag-complete-workspace.php beacadmedia@gmail.com
 */

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\TenantSignupService;

$backend = dirname(__DIR__).'/backend';
require $backend.'/vendor/autoload.php';
$app = require $backend.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = strtolower(trim($argv[1] ?? ''));
if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php diag-complete-workspace.php owner@example.com\n");
    exit(1);
}

$user = User::query()->where('email', $email)->first();
if ($user === null) {
    echo "USER_MISSING\n";
    exit(1);
}

echo 'user_id='.$user->id.PHP_EOL;
echo 'workspace_status='.$user->workspace_status.PHP_EOL;
echo 'needs_workspace='.($user->needsWorkspace() ? 'yes' : 'no').PHP_EOL;

if (! $user->needsWorkspace()) {
    echo "ALREADY_COMPLETE\n";
    exit(0);
}

$slug = 'diag-'.substr(md5((string) microtime(true)), 0, 8);
$answers = [
    'business_name' => 'Diag Salon',
    'trading_name' => 'Diag',
    'slug' => $slug,
    'business_type' => 'boutique',
    'timezone' => 'Europe/London',
    'owner_first_name' => 'Diag',
    'owner_last_name' => 'Owner',
    'owner_email' => $email,
    'owner_whatsapp' => '+447700900123',
    'contact_email' => $email,
    'location_name' => 'Main salon',
    'address_line1' => '1 High Street',
    'city' => 'London',
    'postcode' => 'SW1A 1AA',
    'country' => 'GB',
    'opening_time' => '09:00',
    'closing_time' => '18:00',
    'desired_plan_slug' => 'basic',
    'services' => [[
        'name' => 'Blow dry',
        'category' => 'Hair',
        'description' => 'Test',
        'image_url' => null,
        'duration_minutes' => 45,
        'base_price_cents' => 3500,
    ]],
];

try {
    $result = app(TenantSignupService::class)->completeWorkspace($user, 'PermanentPass99', $answers);
    echo 'SUCCESS tenant='.$result['tenant']->slug.PHP_EOL;
    echo 'token_len='.strlen($result['token']).PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    echo 'FAIL class='.$e::class.PHP_EOL;
    echo 'FAIL message='.$e->getMessage().PHP_EOL;
    echo 'FAIL at='.$e->getFile().':'.$e->getLine().PHP_EOL;
    echo substr($e->getTraceAsString(), 0, 3000).PHP_EOL;
    exit(2);
}
