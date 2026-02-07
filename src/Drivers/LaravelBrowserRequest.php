<?php

declare(strict_types=1);

namespace Pest\Browser\Drivers;

use Illuminate\Http\Request;
use Override;

/**
 * @internal
 */
final class LaravelBrowserRequest extends Request
{
    /**
     * Some applications compare the host against a string value, and expect it
     * without the port (e.g. `example.test`, not `example.test:12345`).
     */
    #[Override]
    public function getHttpHost(): string
    {
        return $this->getHost();
    }

    /**
     * Keep the port in the origin when generating absolute URLs when the port
     * is non-standard (e.g. http://example.test:12345).
     */
    #[Override]
    public function getSchemeAndHttpHost(): string
    {
        $scheme = $this->getScheme();
        $host = $this->getHost();
        $port = $this->getPort();
        $port = is_int($port) ? $port : (is_string($port) ? (int) $port : null);

        $httpHost = $host;
        if ($port !== null && ! $this->isStandardPort($scheme, $port)) {
            $httpHost .= ':'.$port;
        }

        return $scheme.'://'.$httpHost;
    }

    private function isStandardPort(string $scheme, int $port): bool
    {
        if ($scheme === 'http') {
            return $port === 80;
        }

        return $scheme === 'https' && $port === 443;
    }
}
