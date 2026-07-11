<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Em produção, front e back são a mesma origem (proxy do nginx — ver
        // docs/01-architecture.md), então o Sanctum reconhece a requisição como
        // "stateful" pelo Referer. Simulamos isso aqui para exercitar o mesmo
        // caminho de autenticação por cookie/sessão usado pela SPA.
        $this->withHeader('Referer', config('app.url'));
    }
}
