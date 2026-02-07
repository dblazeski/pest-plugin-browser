<?php

declare(strict_types=1);

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use Pest\Browser\Playwright\Playwright;

it('may wait for the domcontentloaded load state', function (): void {
    $previousTimeout = Playwright::timeout();
    Playwright::setTimeout(1_000);

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

    $page = visit('/page-with-slow-resource', [
        'timeout' => 1_000,
        'waitUntil' => 'domcontentloaded',
    ]);

    try {
        $page->waitDomLoaded();
    } finally {
        Playwright::setTimeout($previousTimeout);
    }

    $page->assertSee('Slow page');
});
