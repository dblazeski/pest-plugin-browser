<?php

declare(strict_types=1);

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use PHPUnit\Framework\ExpectationFailedException;

it('may navigate to a page', function (): void {
    Route::get('/page-a', fn (): string => 'page 1');
    Route::get('/page-b', fn (): string => 'page 2');

    $page = visit('/page-a');
    $page->assertSee('page 1');

    $page->navigate('/page-b');
    $page->assertSee('page 2');
});

it('may time out when navigating with waitUntil=load and the page has slow resources', function (): void {
    Route::get('/page-a', fn (): string => 'page 1');

    Route::get('/slow-style.css', function (): ResponseFactory|Response {
        sleep(2);

        return response(
            'body { background: #fff; }',
            200,
            [
                'Content-Type' => 'text/css',
                'Cache-Control' => 'no-store',
            ],
        );
    });

    Route::get('/page-with-slow-resource', fn (): string => <<<'HTML'
        <html>
            <head>
                <link rel="stylesheet" href="/slow-style.css" />
                <title>Slow</title>
            </head>
            <body>
                <h1>Slow page</h1>
            </body>
        </html>
        HTML);

    $page = visit('/page-a');

    // Default `waitUntil` is "load" so this should time out while the image is still loading.
    $page->navigate('/page-with-slow-resource', ['timeout' => 1_000]);
})->throws(ExpectationFailedException::class);

it('may navigate to a page waiting only until domcontentloaded', function (): void {
    Route::get('/page-a', fn (): string => 'page 1');

    Route::get('/slow-style.css', function (): ResponseFactory|Response {
        sleep(2);

        return response(
            'body { background: #fff; }',
            200,
            [
                'Content-Type' => 'text/css',
                'Cache-Control' => 'no-store',
            ],
        );
    });

    Route::get('/page-with-slow-resource', fn (): string => <<<'HTML'
        <html>
            <head>
                <link rel="stylesheet" href="/slow-style.css" />
                <title>Slow</title>
            </head>
            <body>
                <h1>Slow page</h1>
            </body>
        </html>
        HTML);

    $page = visit('/page-a');

    $page->navigateDomLoaded('/page-with-slow-resource', ['timeout' => 1_000])
        ->assertSee('Slow page');
});

it('may navigate forward and back', function (): void {
    Route::get('/page-a', fn (): string => 'page 1');
    Route::get('/page-b', fn (): string => 'page 2');

    $page = visit('/page-a');
    $page->assertSee('page 1');

    $page->navigate('/page-b');
    $page->assertSee('page 2');

    $page->back();
    $page->assertSee('page 1');

    $page->forward();
    $page->assertSee('page 2');
});
