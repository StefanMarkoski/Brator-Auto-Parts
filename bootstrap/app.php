<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // The admin panel is a separate route file, not a folder inside the storefront
        // routes. Keeping the boundary visible at the routing level makes it harder to
        // accidentally hang an admin page off a storefront layout.
        then: function (): void {
            Route::middleware('web')->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // There is no customer login on this shop, so the only login is the admin one.
        // Configured here rather than via Authenticate::redirectUsing(), which is no
        // longer the hook the framework consults — it fell through to route('login')
        // and 500'd.
        $middleware->redirectGuestsTo('/admin/login');
        $middleware->redirectUsersTo('/admin');

        /*
         | BEHIND A TLS TERMINATOR, TRUST IT — BUT ONLY IT.
         |
         | Hosted, Caddy terminates HTTPS and forwards plain HTTP onward. Without this,
         | Laravel reads the scheme off its own connection, concludes the site is http://,
         | and then builds every asset URL, redirect and signed URL as http:// on a page
         | the browser loaded over https — mixed content, plus signed URLs that fail their
         | own signature check because the scheme in them does not match.
         |
         | NEVER '*'. Trusting every proxy means believing whatever X-Forwarded-* headers a
         | visitor chooses to send, which hands them control of the host and scheme the
         | whole application thinks it is running under — that is a global blast radius for
         | one line of convenience.
         |
         | Private ranges only, and the reason that is sufficient rather than lazy: php-fpm
         | publishes NO port (see compose.yaml — the app service has none), so the only
         | thing that can open a connection to it at all is another container on the compose
         | network. Written as literals rather than read from env deliberately: bootstrap
         | runs before the config cache is consulted, and an env() here would evaluate to
         | null on a config:cache'd deployment, silently switching this off on exactly the
         | machine that needs it.
        */
        $middleware->trustProxies(at: [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
