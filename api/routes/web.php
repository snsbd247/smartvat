<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes
|--------------------------------------------------------------------------
| The React SPA is served for all non-API GET routes. Built asset files
| (/assets/*, /favicon.svg, ...) exist on disk and are served by the web
| server directly, so they never reach this catch-all.
*/

Route::get('/{any}', function () {
    return view('spa');
})->where('any', '^(?!api).*$')->name('spa');
