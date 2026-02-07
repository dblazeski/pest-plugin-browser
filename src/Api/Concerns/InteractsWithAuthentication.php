<?php

declare(strict_types=1);

namespace Pest\Browser\Api\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use RuntimeException;

/**
 * @internal
 *
 * @mixin \Pest\Browser\Api\Webpage
 */
trait InteractsWithAuthentication
{
    /**
     * Logs in the given user inside the current browser context.
     *
     * This mimics the ergonomics of Laravel Dusk's "loginAs" without requiring UI logins.
     */
    public function loginAs(Authenticatable|int|string $user, ?string $guard = null): self
    {
        $this->ensureAuthenticationRoutes();

        $userId = $user instanceof Authenticatable
            ? $user->getAuthIdentifier()
            : $user;

        if (! is_int($userId) && ! is_string($userId)) {
            throw new InvalidArgumentException('The user identifier must be a string or integer.');
        }

        $userId = (string) $userId;

        $currentUrl = $this->page->url();

        $loginUrl = '/pest/browser/login/'.$userId;
        if ($guard !== null && $guard !== '') {
            $loginUrl .= '/'.$guard;
        }

        $this->navigate($loginUrl);
        $this->page->goto($currentUrl);

        return $this;
    }

    /**
     * Browser equivalent of Laravel's "actingAs()" test helper.
     *
     * This authenticates the current browser context by issuing a request that
     * sets the session cookie, instead of using a UI login flow.
     */
    public function browserActingAs(Authenticatable|int|string $user, ?string $guard = null): self
    {
        return $this->loginAs($user, $guard);
    }

    /**
     * Logs out the current user from the browser context.
     */
    public function logout(?string $guard = null): self
    {
        $this->ensureAuthenticationRoutes();

        $currentUrl = $this->page->url();

        $logoutUrl = '/pest/browser/logout';
        if ($guard !== null && $guard !== '') {
            $logoutUrl .= '/'.$guard;
        }

        $this->navigate($logoutUrl);
        $this->page->goto($currentUrl);

        return $this;
    }

    /**
     * Browser equivalent of logging out the current user.
     */
    public function browserLogout(?string $guard = null): self
    {
        return $this->logout($guard);
    }

    /**
     * Registers test-only authentication routes (once per PHP process).
     */
    private function ensureAuthenticationRoutes(): void
    {
        static $authenticationRoutesRegistered = false;

        if ($authenticationRoutesRegistered) {
            return;
        }

        if (! function_exists('app_path')) {
            $authenticationRoutesRegistered = true;

            return;
        }

        Route::get('/pest/browser/login/{userId}/{guard?}', function (string $userId, ?string $guard = null): ResponseFactory|Response {
            $guard ??= config('auth.defaults.guard');
            $guard = is_string($guard) && $guard !== '' ? $guard : null;

            if ($guard !== null) {
                $statefulGuard = Auth::guard($guard);
                if (! $statefulGuard instanceof StatefulGuard) {
                    throw new RuntimeException('The configured auth guard does not support stateful authentication.');
                }

                $statefulGuard->loginUsingId((int) $userId);
            } else {
                Auth::loginUsingId((int) $userId);
            }

            request()->session()->regenerate();

            return response('', 204);
        })->middleware('web');

        Route::get('/pest/browser/logout/{guard?}', function (?string $guard = null): ResponseFactory|Response {
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

            return response('', 204);
        })->middleware('web');

        $authenticationRoutesRegistered = true;
    }
}
