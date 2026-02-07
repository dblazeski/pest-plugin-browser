<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

$loginRoute = Route::get('/pest/browser/login/{userId}/{guard?}', function (string $userId, ?string $guard = null): ResponseFactory|Response {
    $guard ??= config('auth.defaults.guard');
    $guard = is_string($guard) && $guard !== '' ? $guard : null;

    if ($guard !== null) {
        $statefulGuard = Auth::guard($guard);
        if (! $statefulGuard instanceof StatefulGuard) {
            throw new RuntimeException('The configured auth guard does not support stateful authentication.');
        }

        $statefulGuard->loginUsingId($userId);
    } else {
        Auth::loginUsingId($userId);
    }

    return response('OK', 200);
})->middleware('web');

$logoutRoute = Route::get('/pest/browser/logout/{guard?}', function (?string $guard = null): ResponseFactory|Response {
    $guard ??= config('auth.defaults.guard');
    $guard = is_string($guard) && $guard !== '' ? $guard : null;

    if ($guard !== null) {
        $statefulGuard = Auth::guard($guard);
        if (! $statefulGuard instanceof StatefulGuard) {
            throw new RuntimeException('The configured auth guard does not support stateful authentication.');
        }

        $statefulGuard->logout();
    } else {
        Auth::logout();
    }

    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return response('OK', 200);
})->middleware('web');

$whoAmIRoute = Route::get('/pest/browser/test-browser-testing', function (): ResponseFactory|Response {
    return response((string) Auth::id(), 200);
})->middleware('web');

return [$loginRoute, $logoutRoute, $whoAmIRoute];
