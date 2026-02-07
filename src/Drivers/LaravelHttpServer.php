<?php

declare(strict_types=1);

namespace Pest\Browser\Drivers;

use Amp\ByteStream\ReadableResourceStream;
use Amp\Http\Cookie\RequestCookie;
use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\HttpServer as AmpHttpServer;
use Amp\Http\Server\HttpServerStatus;
use Amp\Http\Server\Request as AmpRequest;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\SocketHttpServer;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Testing\Concerns\WithoutExceptionHandlingHandler;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector as LaravelRedirector;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Uri;
use Pest\Browser\Contracts\HttpServer;
use Pest\Browser\Exceptions\ServerNotFoundException;
use Pest\Browser\Execution;
use Pest\Browser\GlobalState;
use Pest\Browser\Playwright\Playwright;
use Psr\Log\NullLogger;
use Symfony\Component\Mime\MimeTypes;
use Throwable;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final class LaravelHttpServer implements HttpServer
{
    /**
     * The underlying socket server instance, if any.
     */
    private ?AmpHttpServer $socket = null;

    /**
     * The original asset URL, if set.
     */
    private ?string $originalAssetUrl = null;

    /**
     * The last throwable that occurred during the server's execution.
     */
    private ?Throwable $lastThrowable = null;

    /**
     * The original Laravel redirector instance (used to reset request state).
     */
    private ?LaravelRedirector $originalRedirector = null;

    /**
     * Creates a new laravel http server instance.
     */
    public function __construct(
        public readonly string $host,
        public readonly int $port,
    ) {
        //
    }

    /**
     * Destroy the server instance and stop listening for incoming connections.
     */
    public function __destruct()
    {
        // @codeCoverageIgnoreStart
        // $this->stop();
    }

    /**
     * Rewrite the given URL to match the server's host and port.
     */
    public function rewrite(string $url): string
    {
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = mb_ltrim($url, '/');

            $url = '/'.$url;
        }

        $parts = parse_url($url);
        $queryParameters = [];
        $path = $parts['path'] ?? '/';
        parse_str($parts['query'] ?? '', $queryParameters);

        return (string) Uri::of($this->publicUrl())
            ->withPath($path)
            ->withQuery($queryParameters);
    }

    /**
     * Start the server and listen for incoming connections.
     */
    public function start(): void
    {
        if ($this->socket instanceof AmpHttpServer) {
            return;
        }

        $this->socket = $server = SocketHttpServer::createForDirectAccess(new NullLogger());

        $server->expose("{$this->host}:{$this->port}");
        $server->start(
            new ClosureRequestHandler($this->handleRequest(...)),
            new DefaultErrorHandler(),
        );
    }

    /**
     * Stop the server and close all connections.
     */
    public function stop(): void
    {
        // @codeCoverageIgnoreStart
        if ($this->socket instanceof AmpHttpServer) {
            $this->flush();

            if ($this->socket instanceof AmpHttpServer) {
                if (in_array($this->socket->getStatus(), [HttpServerStatus::Starting, HttpServerStatus::Started], true)) {
                    $this->socket->stop();
                }

                $this->socket = null;
            }
        }
    }

    /**
     * Flush pending requests and close all connections.
     */
    public function flush(): void
    {
        if (! $this->socket instanceof AmpHttpServer) {
            return;
        }

        Execution::instance()->tick();

        $this->lastThrowable = null;
    }

    /**
     * Bootstrap the server and set the application URL.
     */
    public function bootstrap(): void
    {
        $this->start();

        $url = $this->publicUrl();
        $mainUrl = config('app.url');

        $appUrl = is_string($mainUrl) && $mainUrl !== '' ? $mainUrl : $url;

        config(['app.url' => $appUrl]);

        config(['cors.paths' => ['*']]);

        // Browser tests may freeze time; use session cookies (no Expires) so the browser
        // does not discard them as "expired" when the app clock is in the past.
        config([
            'session.expire_on_close' => true,
            // Host-only cookies are required for multi-tenant apps using subdomains.
            // This prevents the session cookie from being shared across tenants.
            'session.domain' => null,
            'session.secure' => false,
        ]);

        if (app()->bound('redirect')) {
            $redirector = app('redirect');

            assert($redirector instanceof LaravelRedirector);

            $this->originalRedirector = $redirector;
        }

        if (app()->bound('url')) {
            $urlGenerator = app('url');

            assert($urlGenerator instanceof UrlGenerator);

            $this->setOriginalAssetUrl($urlGenerator->asset(''));

            $urlGenerator->useOrigin($url);
            $urlGenerator->useAssetOrigin($url);
            $urlGenerator->forceScheme('http');
        }
    }

    /**
     * Get the last throwable that occurred during the server's execution.
     */
    public function lastThrowable(): ?Throwable
    {
        return $this->lastThrowable;
    }

    /**
     * Throws the last throwable if it should be thrown.
     *
     * @throws Throwable
     */
    public function throwLastThrowableIfNeeded(): void
    {
        if (! $this->lastThrowable instanceof Throwable) {
            return;
        }

        $exceptionHandler = app(ExceptionHandler::class);

        if ($exceptionHandler instanceof WithoutExceptionHandlingHandler) {
            throw $this->lastThrowable;
        }
    }

    /**
     * The URL that browser clients should navigate to.
     *
     * Even though the server binds to 127.0.0.1, we must expose a resolvable host
     * so cookies and domain-based routing behave like production.
     */
    private function publicUrl(): string
    {
        if (! $this->socket instanceof AmpHttpServer) {
            throw new ServerNotFoundException('The HTTP server is not running.');
        }

        $host = Playwright::host();

        return sprintf('http://%s:%d', $host ?? $this->host, $this->port);
    }

    /**
     * Sets the original asset URL.
     */
    private function setOriginalAssetUrl(string $url): void
    {
        $this->originalAssetUrl = mb_rtrim($url, '/');
    }

    /**
     * Handle the incoming request and return a response.
     */
    private function handleRequest(AmpRequest $request): Response
    {
        GlobalState::flush();

        if (Execution::instance()->isWaiting() === false) {
            Execution::instance()->tick();
        }

        $uri = $request->getUri();
        $path = in_array($uri->getPath(), ['', '0'], true) ? '/' : $uri->getPath();
        $query = $uri->getQuery() ?? ''; // @phpstan-ignore-line
        $fullPath = $path.($query !== '' ? '?'.$query : '');

        $hostHeader = $request->getHeader('host');
        $hostAndPort = $hostHeader;
        $host = null;
        $port = null;
        if (is_string($hostHeader) && $hostHeader !== '') {
            $parsed = parse_url('http://'.$hostHeader);
            $host = is_string($parsed['host'] ?? null) ? $parsed['host'] : null;
            $port = is_int($parsed['port'] ?? null) ? $parsed['port'] : null;
        }

        $host ??= $uri->getHost() !== '' ? $uri->getHost() : null;
        $port ??= $uri->getPort();

        $scheme = $uri->getScheme() !== '' ? $uri->getScheme() : 'http';
        $host ??= $this->host;
        $port ??= $this->port;
        $hostAndPort ??= $host.':'.$port;

        $absoluteUrl = $scheme.'://'.$hostAndPort.$fullPath;
        $publicOrigin = $scheme.'://'.$host.':'.$port;

        $filepath = public_path($path);
        if (file_exists($filepath) && ! is_dir($filepath)) {
            return $this->asset($filepath, $publicOrigin);
        }

        $kernel = app()->make(HttpKernel::class);

        if ($this->originalRedirector instanceof LaravelRedirector) {
            // The Laravel app instance is long-lived in browser tests; reset the redirector
            // to prevent Livewire's temporary redirector binding leaking between requests.
            app()->instance('redirect', $this->originalRedirector);
        }

        if (app()->bound('tenant')) {
            // The app container is long-lived in browser tests; mimic real request
            // lifecycle by forcing a fresh tenant resolution for every request.
            app()->forgetInstance('tenant');
        }

        if (app()->bound('url')) {
            $urlGenerator = app('url');

            assert($urlGenerator instanceof UrlGenerator);

            $urlGenerator->useOrigin($publicOrigin);
            $urlGenerator->useAssetOrigin($publicOrigin);
            $urlGenerator->forceScheme($scheme);
        }

        $contentType = $request->getHeader('content-type') ?? '';
        $method = mb_strtoupper($request->getMethod());
        $rawBody = (string) $request->getBody();
        $parameters = [];
        if ($method !== 'GET' && str_starts_with(mb_strtolower($contentType), 'application/x-www-form-urlencoded')) {
            parse_str($rawBody, $parameters);
        }
        // Cookie values are not query strings; avoid converting "+" to spaces.
        $cookies = array_map(fn (RequestCookie $cookie): string => rawurldecode($cookie->getValue()), $request->getCookies());
        $cookies = array_merge($cookies, test()->prepareCookiesForRequest()); // @phpstan-ignore-line
        /** @var array<string, string> $serverVariables */
        $serverVariables = test()->serverVariables(); // @phpstan-ignore-line

        $symfonyRequest = Request::create(
            $absoluteUrl,
            $method,
            $parameters,
            $cookies,
            [], // @TODO files...
            $serverVariables,
            $rawBody
        );

        $symfonyRequest->headers->add($request->getHeaders());

        $symfonyRequest->headers->set('Host', $hostAndPort);
        $symfonyRequest->server->set('SERVER_NAME', $host);
        $symfonyRequest->server->set('SERVER_PORT', (string) $port);
        $symfonyRequest->server->set('HTTP_HOST', $hostAndPort);

        $debug = config('app.debug');

        try {
            config(['app.debug' => false]);

            $response = $kernel->handle($laravelRequest = LaravelBrowserRequest::createFromBase($symfonyRequest));
        } catch (Throwable $e) {
            $this->lastThrowable = $e;

            throw $e;
        } finally {
            config(['app.debug' => $debug]);
        }

        $kernel->terminate($laravelRequest, $response);

        if (property_exists($response, 'exception') && $response->exception !== null) {
            assert($response->exception instanceof Throwable);

            $this->lastThrowable = $response->exception;
        }

        $content = $response->getContent();

        if ($content === false) {
            try {
                ob_start();
                $response->sendContent();
            } finally {
                // @phpstan-ignore-next-line
                $content = mb_trim(ob_get_clean());
            }
        }

        return new Response(
            $response->getStatusCode(),
            $response->headers->all(), // @phpstan-ignore-line
            $content,
        );
    }

    /**
     * Return an asset response.
     */
    private function asset(string $filepath, string $publicOrigin): Response
    {
        $file = fopen($filepath, 'r');

        if ($file === false) {
            return new Response(404);
        }

        $mimeTypes = new MimeTypes();
        $contentType = $mimeTypes->getMimeTypes(pathinfo($filepath, PATHINFO_EXTENSION));

        $contentType = $contentType[0] ?? 'application/octet-stream';

        if (str_ends_with($filepath, '.js')) {
            $temporaryStream = fopen('php://temp', 'r+');
            assert($temporaryStream !== false, 'Failed to open temporary stream.');

            // @phpstan-ignore-next-line
            $temporaryContent = fread($file, (int) filesize($filepath));

            assert($temporaryContent !== false, 'Failed to open temporary stream.');

            $content = $this->rewriteAssetUrl($temporaryContent, $publicOrigin);

            fwrite($temporaryStream, $content);

            rewind($temporaryStream);

            $file = $temporaryStream;
        }

        return new Response(200, [
            'Content-Type' => $contentType,
        ], new ReadableResourceStream($file));
    }

    /**
     * Rewrite the asset URL in the given content.
     */
    private function rewriteAssetUrl(string $content, string $publicOrigin): string
    {
        if ($this->originalAssetUrl === null) {
            return $content;
        }

        return str_replace($this->originalAssetUrl, $publicOrigin, $content);
    }
}
