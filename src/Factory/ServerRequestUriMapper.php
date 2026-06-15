<?php

declare(strict_types=1);

namespace Waffle\Commons\Http\Factory;

use Psr\Http\Message\UriInterface;
use Waffle\Commons\Http\Uri;

/**
 * Reconstructs a PSR-7 {@see UriInterface} from a server-parameters array (`$_SERVER`).
 *
 * Extracted from {@see GlobalsFactory} (CPLX-04) so the URI-assembly concern
 * (scheme detection, host/port splitting, user-info) stays small and independently
 * testable. It NEVER reads the superglobal itself — the server array is passed in by
 * the factory (the single allowed reader), honouring the statelessness mandate.
 *
 * Trusted-host enforcement is NOT performed here; `TrustedHostMiddleware` rejects
 * Host-Header injection downstream in the PSR-15 stack.
 */
final class ServerRequestUriMapper
{
    /**
     * @param array<string, string> $server
     */
    public function map(array $server): UriInterface
    {
        $scheme = $this->detectScheme($server);
        [$host, $port] = $this->extractHostAndPort($server, $scheme);

        $path = explode(separator: '?', string: $server['REQUEST_URI'] ?? '/', limit: 2)[0];
        $query = $server['QUERY_STRING'] ?? '';
        $userInfo = $this->extractUserInfo($server);

        $uriString = $scheme . '://';
        if ('' !== $userInfo) {
            $uriString .= $userInfo . '@';
        }
        $uriString .= $host;
        if (!('http' === $scheme && 80 === $port || 'https' === $scheme && 443 === $port)) {
            $uriString .= ':' . $port;
        }
        $uriString .= $path;
        if ('' !== $query) {
            $uriString .= '?' . $query;
        }

        return new Uri($uriString);
    }

    /**
     * Detects the request scheme (http or https) from $_SERVER.
     *
     * @param array<string, string> $server
     */
    private function detectScheme(array $server): string
    {
        if (array_key_exists('HTTPS', $server) && ('on' === $server['HTTPS'] || 1 === (int) $server['HTTPS'])) {
            return 'https';
        }
        return 'http';
    }

    /**
     * Extracts host and port, splitting a `host:port` HTTP_HOST when present.
     *
     * @param array<string, string> $server
     * @return array{0: string, 1: int}
     */
    private function extractHostAndPort(array $server, string $scheme): array
    {
        $host = $server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? 'localhost';
        $matches = [];

        if (1 === preg_match('/^(.+):(\d+)$/', $host, $matches)) {
            return [$matches[1] ?? $host, (int) ($matches[2] ?? 80)];
        }

        $port = (int) ($server['SERVER_PORT'] ?? ('http' === $scheme ? 80 : 443));
        return [$host, $port];
    }

    /**
     * Builds the `user[:pass]` user-info segment from Basic-auth $_SERVER keys.
     *
     * @param array<string, string> $server
     */
    private function extractUserInfo(array $server): string
    {
        $user = array_key_exists('PHP_AUTH_USER', $server) ? $server['PHP_AUTH_USER'] : null;
        if (null === $user) {
            return '';
        }
        $pass = array_key_exists('PHP_AUTH_PW', $server) ? $server['PHP_AUTH_PW'] : null;
        return $user . (null !== $pass ? ':' . $pass : '');
    }
}
