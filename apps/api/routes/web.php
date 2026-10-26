<?php

use Illuminate\Support\Facades\Route;

// The built Vue app (apps/web → public/app) owns every non-API path.
Route::get('/{any?}', function () {
    $index = public_path('app/index.html');

    abort_unless(is_file($index), 503, 'The web app has not been built yet. Run `pnpm build` from the repository root.');

    return response()->file($index, ['Cache-Control' => 'no-cache, no-store, must-revalidate']);
})->where('any', '^(?!api/|sanctum/|up$|app/).*$');
