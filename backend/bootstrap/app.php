<?php

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
        // sem views (ver docs/01-architecture.md). Para requisições que já
        // pedem JSON isso nunca é consultado (o unauthenticated() do handler
        // responde 401 direto); mas para a única rota com HTML de verdade
        // (o comprovante térmico, ver routes/web.php) retornar null aqui
        // caía no fallback `?? route('login')` do próprio Laravel — que
        // também não existe — e virava 500 em vez de simplesmente mandar o
        // guest pra tela de login da SPA.
        $middleware->redirectGuestsTo(fn () => '/login');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // API-only por decisão de arquitetura (ver docs/01-architecture.md) — as
        // únicas exceções são as rotas Blade (comprovante térmico e impressão de
        // etiquetas), que não passam por fluxos de autenticação/erro que
        // precisariam de HTML. Sem isso, o guest que bate num endpoint protegido
        // recebe um redirect para uma rota "login" que não existe (500), em vez
        // de 401 JSON.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => ! $request->routeIs('sales.receipt', 'labels.print'));
    })->create();
