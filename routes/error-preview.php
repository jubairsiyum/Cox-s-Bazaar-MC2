<?php

use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
 | LOCAL ONLY. Lets you open /_errors/404, /_errors/500, /_errors/503 ... in the browser
 | (APP_DEBUG=true would otherwise replace real 500s with the debug page).
 | Include from routes/web.php:  require __DIR__.'/error-preview.php';
 */
if (app()->environment('local')) {
    Route::get('/_errors/{code}', function (int $code) {
        abort_unless($code >= 400 && $code <= 599, 404);

        $e = new HttpException($code, '', null, $code === 429 ? ['Retry-After' => 20] : []);
        $view = view()->exists("errors.$code") ? "errors.$code" : ($code >= 500 ? 'errors.5xx' : 'errors.4xx');

        return response()->view($view, ['exception' => $e, 'retryAfter' => 20], $code);
    })->whereNumber('code');
}
