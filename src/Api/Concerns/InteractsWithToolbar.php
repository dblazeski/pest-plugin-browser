<?php

declare(strict_types=1);

namespace Pest\Browser\Api\Concerns;

use Pest\Browser\Api\Webpage;
use Pest\Browser\Support\ComputeUrl;

/**
 * @mixin Webpage
 */
trait InteractsWithToolbar
{
    /**
     * Reloads the current page.
     */
    public function refresh(): self
    {
        $this->page->reload();

        return $this;
    }

    /**
     * Navigates to the given URL.
     *
     * @param  array<string, mixed>  $options
     */
    public function navigate(string $url, array $options = []): self
    {
        if (str_starts_with($url, '/')) {
            $currentUrl = $this->page->url();
            $parts = parse_url($currentUrl);

            $scheme = is_array($parts) ? ($parts['scheme'] ?? null) : null;
            $host = is_array($parts) ? ($parts['host'] ?? null) : null;
            $port = is_array($parts) ? ($parts['port'] ?? null) : null;

            if (is_string($scheme) && $scheme !== '' && is_string($host) && $host !== '') {
                $url = $scheme.'://'.$host.(is_int($port) ? ':'.$port : '').$url;
            } else {
                $url = ComputeUrl::from($url);
            }
        } else {
            $url = ComputeUrl::from($url);
        }

        $this->page->goto($url, $options);

        return $this;
    }

    /**
     * Navigates to the given URL, waiting until "domcontentloaded".
     *
     * @param  array<string, mixed>  $options
     */
    public function navigateDomLoaded(string $url, array $options = []): self
    {
        $options['waitUntil'] = 'domcontentloaded';

        return $this->navigate($url, $options);
    }

    /**
     * Navigates to the next page in the history.
     */
    public function forward(): self
    {
        $this->page->forward();

        return $this;
    }

    /**
     * Navigates to the previous page in the history.
     */
    public function back(): self
    {
        $this->page->back();

        return $this;
    }
}
