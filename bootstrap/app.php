<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Render (and platforms like it — Heroku, Fly.io) terminates TLS at
        // its edge and forwards the request to this container over plain
        // HTTP, with the original scheme carried in X-Forwarded-Proto. This
        // container is never directly reachable from the internet — Render's
        // edge is the only ingress — so trusting every incoming connection
        // as a proxied one (`at: '*'`) is correct here, the same way it is
        // on any PaaS with a dynamic, unlisted edge IP range. Without this,
        // Request::isSecure() is always false behind the proxy, and every
        // generated asset/route URL (Vite included) comes back as
        // http://, which the browser then blocks as mixed content on an
        // https:// page.
        $middleware->trustProxies(at: '*');

        $middleware->append(AssignRequestId::class);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'staff' => EnsureUserIsStaff::class,
            'admin' => EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
