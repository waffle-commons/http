<?php

declare(strict_types=1);

namespace Waffle\Commons\Http;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;

/**
 * PSR-7 UriInterface implementation.
 *
 * @see https://www.php-fig.org/psr/psr-7/#35-psrhttpmessageuriinterface
 */
// @mago-ignore lint:cyclomatic-complexity
class Uri implements UriInterface
{
    private string $scheme = '';
    private string $userInfo = '';
    private string $host = '';
    private ?int $port = null;
    private string $path = '';
    private string $query = '';
    private string $fragment = '';

    /**
     * Standard ports for various schemes.
     * @var array<string, int>
     */
    private const STANDARD_PORTS = [
        'http' => 80,
        'https' => 443,
        'ftp' => 21,
        'gopher' => 70,
        'nntp' => 119,
        'news' => 119,
        'telnet' => 23,
        'tn3270' => 23,
        'imap' => 143,
        'pop' => 110,
        'ldap' => 389,
    ];

    /**
     * @param string $uri The URI string to parse.
     * @throws InvalidArgumentException If the URI cannot be parsed.
     */
    public function __construct(string $uri = '')
    {
        if ('' !== $uri) {
            $parts = parse_url($uri);
            if (false === $parts) {
                throw new InvalidArgumentException(sprintf('Unable to parse URI: "%s".', $uri));
            }
            // Applies the parsed parts to the object properties
            $this->applyParts($parts);
        }
    }

    /**
     * Applies parsed parts (from parse_url) to the instance properties.
     *
     * @param array $parts Associative array from parse_url.
     */
    private function applyParts(array $parts): void
    {
        $this->scheme = array_key_exists('scheme', $parts) ? $this->filterScheme((string) $parts['scheme']) : '';
        $this->userInfo = array_key_exists('user', $parts)
            ? $this->filterUserInfo(
                (string) $parts['user'],
                array_key_exists('pass', $parts) ? (string) $parts['pass'] : null,
            )
            : '';
        $this->host = array_key_exists('host', $parts) ? $this->filterHost((string) $parts['host']) : '';
        $this->port = array_key_exists('port', $parts) ? $this->filterPort((int) $parts['port']) : null;
        $this->path = array_key_exists('path', $parts) ? $this->filterPath((string) $parts['path']) : '';
        $this->query = array_key_exists('query', $parts) ? $this->filterQuery((string) $parts['query']) : '';
        $this->fragment = array_key_exists('fragment', $parts)
            ? $this->filterFragment((string) $parts['fragment'])
            : '';
    }

    // --- Filter Methods for normalization ---

    /**
     * Filters and normalizes the scheme (lowercase).
     */
    private function filterScheme(string $scheme): string
    {
        return strtolower($scheme);
    }

    /**
     * Filters and assembles user info.
     */
    private function filterUserInfo(string $user, #[\SensitiveParameter] ?string $password = null): string
    {
        $userInfo = $user;
        if (null !== $password && '' !== $password) {
            $userInfo .= ':' . $password;
        }
        return $userInfo;
    }

    /**
     * Filters and normalizes the host (lowercase).
     */
    private function filterHost(string $host): string
    {
        return strtolower($host);
    }

    /**
     * Validates the port.
     * @throws InvalidArgumentException For invalid ports.
     */
    private function filterPort(?int $port): ?int
    {
        if (null === $port) {
            return null;
        }

        if ($port < 1 || $port > 65_535) {
            throw new InvalidArgumentException(sprintf('Invalid port: %d. Must be between 1 and 65535.', $port));
        }

        return $port;
    }

    /**
     * Filters and encodes the path.
     */
    private function filterPath(string $path): string
    {
        // Encodes unauthorized characters in a path, except '/'
        $path =
            preg_replace_callback(
                '/(?:[^a-zA-Z0-9_\-\.~!\$&\'\(\)\*\+,;=:@\/%]+|%(?![A-Fa-f0-9]{2}))/',
                static fn(array $matches): string => rawurlencode($matches[0] ?? ''),
                $path,
            ) ?? '';
        // Ensures a non-empty path starts with a '/'
        return $path === '' || str_starts_with($path, '/') ? $path : '/' . $path;
    }

    /**
     * Filters and encodes the query string.
     */
    private function filterQuery(string $query): string
    {
        // Encodes unauthorized characters, except '/' and '?'
        $query =
            preg_replace_callback(
                '/(?:[^a-zA-Z0-9_\-\.~!\$&\'\(\)\*\+,;=:@\/\?%]+|%(?![A-Fa-f0-9]{2}))/',
                static fn(array $matches): string => rawurlencode($matches[0] ?? ''),
                $query,
            ) ?? '';
        // Removes leading '?' if present
        return ltrim(string: $query, characters: '?');
    }

    /**
     * Filters and encodes the fragment.
     */
    private function filterFragment(string $fragment): string
    {
        // Encodes unauthorized characters, except '/', '?' and '#'
        $fragment =
            preg_replace_callback(
                '/(?:[^a-zA-Z0-9_\-\.~!\$&\'\(\)\*\+,;=:@\/\?%]+|%(?![A-Fa-f0-9]{2}))/',
                static fn(array $matches): string => rawurlencode($matches[0] ?? ''),
                $fragment,
            ) ?? '';
        // Removes leading '#' if present
        return ltrim(string: $fragment, characters: '#');
    }

    /**
     * Applies PSR-7 path normalization rules based on authority presence.
     */
    private function buildNormalizedPath(string $authority, string $path): string
    {
        if ('' !== $authority && $path !== '' && !str_starts_with($path, '/')) {
            return '/' . $path;
        }
        if ('' === $authority && str_starts_with($path, '//')) {
            return '/' . ltrim(string: $path, characters: '/');
        }
        if ($path === '' && '' !== $authority) {
            return '/';
        }
        return $path;
    }

    // --- Public Methods ---

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function __toString(): string
    {
        $uri = '';

        // 1. Scheme
        if ('' !== $this->scheme) {
            $uri .= $this->scheme . ':';
        }

        // 2. Authority
        $authority = $this->getAuthority();
        if ('' !== $authority || 'file' === $this->scheme) {
            $uri .= '//' . $authority;
        }

        // 3. Path (PSR-7 section 4.1 normalization)
        $path = $this->buildNormalizedPath($authority, $this->path);

        $uri .= $path;

        // 4. Query
        if ('' !== $this->query) {
            $uri .= '?' . $this->query;
        }

        // 5. Fragment
        if ('' !== $this->fragment) {
            $uri .= '#' . $this->fragment;
        }

        return $uri;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getAuthority(): string
    {
        $authority = $this->host;
        if ('' === $authority) {
            return ''; // No authority if no host
        }

        // Adds user info if present
        if ('' !== $this->userInfo) {
            $authority = $this->userInfo . '@' . $authority;
        }

        // Adds port if defined AND non-standard
        $port = $this->getPort();
        if (null !== $port) {
            $authority .= ':' . $port;
        }

        return $authority;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getPort(): ?int
    {
        if (null === $this->port) {
            return null; // Port not defined
        }

        // If a standard port is defined for the scheme and it matches, return null
        if (
            array_key_exists($this->scheme, self::STANDARD_PORTS)
            && $this->port === self::STANDARD_PORTS[$this->scheme]
        ) {
            return null;
        }

        // Returns the non-standard port
        return $this->port;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getFragment(): string
    {
        return $this->fragment;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function withScheme(string $scheme): UriInterface
    {
        $new = clone $this;
        $new->scheme = $this->filterScheme($scheme);
        // Re-validates port against new scheme
        $new->port = $this->filterPort($this->port);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function withUserInfo(string $user, #[\SensitiveParameter] ?string $password = null): UriInterface
    {
        $new = clone $this;
        $new->userInfo = $this->filterUserInfo($user, $password);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function withHost(string $host): UriInterface
    {
        if ($host === $this->host) {
            return $this;
        }
        $new = clone $this;
        $new->host = $this->filterHost($host);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function withPort(?int $port): UriInterface
    {
        $new = clone $this;
        $new->port = $this->filterPort($port);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function withPath(string $path): UriInterface
    {
        if ($path === $this->path) {
            return $this;
        }
        $new = clone $this;
        $new->path = $this->filterPath($path);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function withQuery(string $query): UriInterface
    {
        if ($query === $this->query) {
            return $this;
        }
        $new = clone $this;
        $new->query = $this->filterQuery($query);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function withFragment(string $fragment): UriInterface
    {
        if ($fragment === $this->fragment) {
            return $this;
        }
        $new = clone $this;
        $new->fragment = $this->filterFragment($fragment);
        return $new;
    }
}
