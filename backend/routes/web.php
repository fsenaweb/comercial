<?php

use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Única rota Blade do sistema (ver docs/01-architecture.md e docs/02-design-patterns.md).
// routes/web.php já roda dentro do grupo de middleware 'web' automático do Laravel,
// que já inclui StartSession — então o guard 'sanctum' já enxerga a sessão de
// cookie da SPA sozinho (Guard::__invoke() tenta o guard 'web' primeiro, que só
// depende da sessão já iniciada, não de EnsureFrontendRequestsAreStateful ter
// rodado). Chegamos a adicionar EnsureFrontendRequestsAreStateful aqui, mas isso
// duplicava o StartSession (um pelo grupo 'web', outro pelo próprio middleware) e
// deslogava o usuário ao abrir o comprovante — bug real encontrado testando no
// navegador. `auth:sanctum` sozinho já basta.
Route::middleware('auth:sanctum')
    ->get('/sales/{sale}/receipt', [ReceiptController::class, 'show'])
    ->name('sales.receipt');
