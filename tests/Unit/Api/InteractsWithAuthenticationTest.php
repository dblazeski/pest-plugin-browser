<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Pest\Browser\Api\Concerns\InteractsWithAuthentication;

final class FakeWebpage
{
    use InteractsWithAuthentication;

    public ?string $navigatedTo = null;

    public function navigate(string $url): self
    {
        $this->navigatedTo = $url;

        return $this;
    }
}

final readonly class FakeUser implements Authenticatable
{
    public function __construct(
        private int|string $id,
    ) {
        //
    }

    public function getAuthIdentifierName()
    {
        return 'id';
    }

    public function getAuthIdentifier()
    {
        return $this->id;
    }

    public function getAuthPasswordName()
    {
        return 'password';
    }

    public function getAuthPassword()
    {
        return '';
    }

    public function getRememberToken()
    {
        return '';
    }

    public function setRememberToken($value): void
    {
        //
    }

    public function getRememberTokenName()
    {
        return 'remember_token';
    }
}

it('builds the login URL and navigates to it', function (): void {
    $page = new FakeWebpage();

    $page->loginAs(123);

    expect($page->navigatedTo)->toBe('/pest/browser/login/123');
});

it('adds the guard to the login URL when provided', function (): void {
    $page = new FakeWebpage();

    $page->loginAs(123, 'web');

    expect($page->navigatedTo)->toBe('/pest/browser/login/123/web');
});

it('accepts an Authenticatable user instance', function (): void {
    $page = new FakeWebpage();

    $user = new FakeUser('abc-123');

    $page->loginAs($user);

    expect($page->navigatedTo)->toBe('/pest/browser/login/abc-123');
});

it('aliases browserActingAs() to loginAs()', function (): void {
    $page = new FakeWebpage();

    $page->browserActingAs(321);

    expect($page->navigatedTo)->toBe('/pest/browser/login/321');
});

it('builds the logout URL and navigates to it', function (): void {
    $page = new FakeWebpage();

    $page->logout();

    expect($page->navigatedTo)->toBe('/pest/browser/logout');
});

it('adds the guard to the logout URL when provided', function (): void {
    $page = new FakeWebpage();

    $page->logout('web');

    expect($page->navigatedTo)->toBe('/pest/browser/logout/web');
});

it('aliases browserLogout() to logout()', function (): void {
    $page = new FakeWebpage();

    $page->browserLogout();

    expect($page->navigatedTo)->toBe('/pest/browser/logout');
});
