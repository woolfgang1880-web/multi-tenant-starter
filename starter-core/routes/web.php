<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/docs/openapi.yaml', function () {
    $path = base_path('docs/openapi/openapi.yaml');

    abort_unless(is_readable($path), 404);

    return response()->file($path, [
        'Content-Type' => 'application/yaml; charset=utf-8',
        'Cache-Control' => 'public, max-age=300',
    ]);
})->name('docs.openapi');

Route::view('/docs/api', 'openapi.swagger', [
    'specUrl' => url('/docs/openapi.yaml'),
])->name('docs.swagger');
