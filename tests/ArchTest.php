<?php

declare(strict_types=1);

arch()
    ->expect(['Illuminate', 'Laravel', 'Livewire'])
    ->toOnlyBeUsedIn([
        Pest\Browser\Api\Livewire::class,
        Pest\Browser\Api\TestableLivewire::class,
        Pest\Browser\Api\Concerns\InteractsWithAuthentication::class,
        Pest\Browser\Cleanables\Livewire::class,
        Pest\Browser\Drivers\LaravelHttpServer::class,
        Pest\Browser\Drivers\LaravelBrowserRequest::class,
        'Workbench',
    ]);
