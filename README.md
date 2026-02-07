This repository contains the Pest Plugin for Browser.

> If you want to start testing your application with Pest, visit the main **[Pest Repository](https://github.com/pestphp/pest)**.

- Explore our docs at **[pestphp.com »](https://pestphp.com)**
- Follow the creator Nuno Maduro:
    - YouTube: **[youtube.com/@nunomaduro](https://www.youtube.com/@nunomaduro)** — Videos every weekday
    - Twitch: **[twitch.tv/enunomaduro](https://www.twitch.tv/enunomaduro)** — Streams (almost) every weekday
    - Twitter / X: **[x.com/enunomaduro](https://x.com/enunomaduro)**
    - LinkedIn: **[linkedin.com/in/nunomaduro](https://www.linkedin.com/in/nunomaduro)**
    - Instagram: **[instagram.com/enunomaduro](https://www.instagram.com/enunomaduro)**
    - Tiktok: **[tiktok.com/@enunomaduro](https://www.tiktok.com/@enunomaduro)**

## Browser Authentication Helpers (Laravel)

When running browser tests against a Laravel app, you can authenticate the current browser context without going through the login UI:

```php
use App\Models\User;

it('shows the dashboard', function () {
    $user = User::factory()->create();

    $page = visit('/dashboard')->browserActingAs($user);

    $page->assertSee('Dashboard');
});
```

Available helpers:
- `loginAs($user, $guard = null)` / `logout($guard = null)`
- `browserActingAs($user, $guard = null)` / `browserLogout($guard = null)`

Under the hood, this uses internal test-only routes (registered at runtime) to set the session cookie for the current browser context:
- `GET /pest/browser/login/{userId}/{guard?}`
- `GET /pest/browser/logout/{guard?}`

Pest is an open-sourced software licensed under the **[MIT license](https://opensource.org/licenses/MIT)**.
