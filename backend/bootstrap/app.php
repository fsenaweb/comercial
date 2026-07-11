<?php

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        $middleware->alias(['role' => EnsureUserHasRole::class]);

        // Laravel registra por padrão um redirect para a rota nomeada "login"
        // quando um guest bate num endpoint protegido sem pedir JSON
        // explicitamente. Não existe (nem existirá) essa rota aqui — API-only,
        // sem views (ver docs/01-architecture.md) — então isso derrubava com
        // RouteNotFoundException em vez de devolver 401. Sempre 401 JSON.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // API-only por decisão de arquitetura (ver docs/01-architecture.md) — a
        // única exceção é a rota Blade do comprovante térmico, que não passa por
        // fluxos de autenticação/erro que precisariam de HTML. Sem isso, o guest
        // que bate num endpoint protegido recebe um redirect para uma rota
        // "login" que não existe (500), em vez de 401 JSON.
        $exceptions->shouldRenderJsonWhen(fn () => true);
    })->create();
