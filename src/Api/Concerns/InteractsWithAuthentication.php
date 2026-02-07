<?php

declare(strict_types=1);

namespace Pest\Browser\Api\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
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

        $loginUrl = '/pest/browser/login/'.$userId;
        if ($guard !== null && $guard !== '') {
            $loginUrl .= '/'.$guard;
        }

        $this->navigate($loginUrl);

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

        $logoutUrl = '/pest/browser/logout';
        if ($guard !== null && $guard !== '') {
            $logoutUrl .= '/'.$guard;
        }

        $this->navigate($logoutUrl);

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
        static $routerObjectId = null;

        if (! function_exists('app_path')) {
            return;
        }

        if (function_exists('app') && app()->environment('testing') === false) {
            throw new RuntimeException('Pest browser authentication routes are only available in the testing environment.');
        }

        $router = app('router');
        if (! $router instanceof Router) {
            return;
        }

        $currentRouterObjectId = spl_object_id($router);
        if ($routerObjectId === $currentRouterObjectId) {
            return;
        }

        $routesFile = __DIR__.'/../../../routes/testing.php';
        if (! is_file($routesFile)) {
            throw new RuntimeException('Unable to locate the Pest browser testing routes file.');
        }

        $routes = require $routesFile;
        if (! is_array($routes) || count($routes) !== 3) {
            throw new RuntimeException('The Pest browser testing routes file must return the expected route definitions.');
        }

        [$loginRoute, $logoutRoute, $whoAmIRoute] = $routes;

        if (! $loginRoute instanceof Route) {
            throw new RuntimeException('The Pest browser testing routes file must return Illuminate\\Routing\\Route instances.');
        }
        if (! $logoutRoute instanceof Route) {
            throw new RuntimeException('The Pest browser testing routes file must return Illuminate\\Routing\\Route instances.');
        }
        if (! $whoAmIRoute instanceof Route) {
            throw new RuntimeException('The Pest browser testing routes file must return Illuminate\\Routing\\Route instances.');
        }

        $routes = $router->getRoutes();
        if ($routes instanceof RouteCollection) {
            // Prepend the auth routes so they are not shadowed by greedy app routes
            // (e.g. `{service_slug}` with `.*` in multi-tenant apps).
            $newRoutes = new RouteCollection();
            $newRoutes->add($loginRoute);
            $newRoutes->add($logoutRoute);
            $newRoutes->add($whoAmIRoute);

            /** @var Route $route */
            foreach ($routes as $route) {
                if ($route === $loginRoute) {
                    continue;
                }
                if ($route === $logoutRoute) {
                    continue;
                }
                if ($route === $whoAmIRoute) {
                    continue;
                }

                $newRoutes->add($route);
            }

            $router->setRoutes($newRoutes);
        }

        $routerObjectId = $currentRouterObjectId;
    }
}
