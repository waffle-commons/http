<?php

declare(strict_types=1);

namespace Waffle\Commons\Http\Factory;

/**
 * Maps a server-parameters array (`$_SERVER`) into a normalised PSR-7 header map.
 *
 * Extracted from {@see GlobalsFactory} (CPLX-04) so each `$_SERVER` concern stays
 * small and independently testable. It NEVER reads the superglobal itself — the
 * server array is passed in by the factory (the single allowed reader), honouring
 * the FrankenPHP statelessness mandate.
 */
final class ServerRequestHeadersMapper
{
    /**
     * @param array<string, string> $server
     * @return array<string, string>
     */
    public function map(array $server): array
    {
        $headers = [];
        foreach ($server as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $headerName = str_replace(
                    search: '_',
                    replace: '-',
                    subject: strtolower(substr(string: $name, offset: 5)),
                );
                $headers[$headerName] = $value;
                continue;
            }
            if (in_array(needle: $name, haystack: ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], strict: true)) {
                $headerName = str_replace(search: '_', replace: '-', subject: strtolower($name));
                $headers[$headerName] = $value;
            }
        }

        $this->resolveAuthorizationHeader($headers, $server);

        return $headers;
    }

    /**
     * Resolves the authorization header from the various $_SERVER sources a SAPI
     * may use (mod_rewrite redirect, Basic, Digest).
     *
     * @param array<string, string> $headers
     * @param array<string, string> $server
     */
    private function resolveAuthorizationHeader(array &$headers, array $server): void
    {
        if (array_key_exists('authorization', $headers)) {
            return;
        }
        if (array_key_exists('REDIRECT_HTTP_AUTHORIZATION', $server)) {
            $headers['authorization'] = $server['REDIRECT_HTTP_AUTHORIZATION'];
            return;
        }
        if (array_key_exists('PHP_AUTH_USER', $server)) {
            $basicAuth = base64_encode($server['PHP_AUTH_USER'] . ':' . ($server['PHP_AUTH_PW'] ?? ''));
            $headers['authorization'] = 'Basic ' . $basicAuth;
            return;
        }
        if (array_key_exists('PHP_AUTH_DIGEST', $server)) {
            $headers['authorization'] = $server['PHP_AUTH_DIGEST'];
        }
    }
}
