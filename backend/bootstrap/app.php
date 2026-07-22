<?php

use App\Shared\Middleware\CorrelationId;
use App\Shared\Middleware\EnsurePermission;
use App\Shared\Middleware\EnsurePlatformAdmin;
use App\Shared\Middleware\LoadTeamMember;
use App\Shared\Middleware\RequireTenant;
use App\Shared\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            CorrelationId::class,
        ]);

        $middleware->alias([
            'tenant.resolve' => ResolveTenant::class,
            'tenant.require' => RequireTenant::class,
            'team.member' => LoadTeamMember::class,
            'permission' => EnsurePermission::class,
            'platform.admin' => EnsurePlatformAdmin::class,
        ]);

        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
