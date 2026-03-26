<?php

namespace Tests;

use Illuminate\Testing\TestResponse;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * El RequestGuard de Sanctum cachea el usuario; entre peticiones HTTP en el mismo
     * test hay que olvidar guards para no reutilizar un usuario ya resuelto.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null): TestResponse
    {
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);

        $this->app['auth']->forgetGuards();

        return $response;
    }
}
