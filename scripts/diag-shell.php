<?php

/**
 * Production diagnostic: GET /api/v1/shell for a user email (issues a temp token).
 *
 * Usage: php scripts/diag-shell.php beacadmedia@gmail.com
 */

use App\Domains\Identity\Models\User;
use Illuminate\Http\Request;

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

$token = $user->createToken('diag-shell')->plainTextToken;
$member = $user->resolveActiveTeamMember();
$slug = $member?->tenant?->slug;

echo 'user_id='.$user->id.PHP_EOL;
echo 'workspace_status='.($user->workspace_status ?? 'null').PHP_EOL;
echo 'tenant_slug='.($slug ?: 'null').PHP_EOL;
echo 'resolveActiveTeamMember='.($member ? $member->id : 'null').PHP_EOL;

try {
    $tm = $user->fresh()->currentTeamMember;
    echo 'currentTeamMember='.($tm ? $tm->id : 'null').PHP_EOL;
} catch (Throwable $e) {
    echo 'currentTeamMember_FAIL='.$e->getMessage().PHP_EOL;
}

$request = Request::create('/api/v1/shell', 'GET', [], [], [], [
    'HTTP_ACCEPT' => 'application/json',
    'HTTP_AUTHORIZATION' => 'Bearer '.$token,
    'HTTP_X_TENANT_SLUG' => $slug ?? '',
]);
$request->headers->set('Accept', 'application/json');
$request->headers->set('Authorization', 'Bearer '.$token);
if ($slug) {
    $request->headers->set('X-Tenant-Slug', $slug);
}

try {
    $response = $kernel->handle($request);
    echo 'status='.$response->getStatusCode().PHP_EOL;
    echo 'body='.substr((string) $response->getContent(), 0, 2000).PHP_EOL;
} catch (Throwable $e) {
    echo 'FAIL class='.$e::class.PHP_EOL;
    echo 'FAIL message='.$e->getMessage().PHP_EOL;
    echo 'FAIL at='.$e->getFile().':'.$e->getLine().PHP_EOL;
}

$user->tokens()->where('name', 'diag-shell')->delete();
