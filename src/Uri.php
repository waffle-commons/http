<?php

declare(strict_types=1);

namespace Waffle\Commons\Http;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;

class Uri implements UriInterface
{
    private string $scheme = '';
    private string $userInfo = '';
    private string $host = '';
    private null|int $port = null;
    private string $path = '';
    private string $query = '';
    private string $fragment = '';

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
     * @param string $uri
     */
    public function __construct(string $uri = '')
    {
        if ('' !== $uri) {
            $parts = parse_url($uri);
            if (false === $parts) {
                throw new InvalidArgumentException(sprintf('Unable to parse URI: "%s".', $uri));
            }
            $this->applyParts($parts);
        }
    }

    /**
     * @param array $parts
     * @return void
     */
    private function applyParts(array $parts): void
    {
        $this->scheme = isset($parts['scheme']) ? $this->filterScheme($parts['scheme']) : '';
        $this->userInfo = isset($parts['user']) ? $this->filterUserInfo($parts['user'], $parts['pass'] ?? null) : '';
        $this->host = isset($parts['host']) ? $this->filterHost($parts['host']) : '';
        $this->port = isset($parts['port']) ? $this->filterPort($parts['port']) : null;
        $this->path = isset($parts['path']) ? $this->filterPath($parts['path']) : '';
        $this->query = isset($parts['query']) ? $this->filterQuery($parts['query']) : '';
        $this->fragment = isset($parts['fragment']) ? $this->filterFragment($parts['fragment']) : '';
    }

    /**
     * @param string $scheme
     * @return string
     */
    private function filterScheme(string $scheme): string
    {
        return strtolower($scheme);
    }

    /**
     * @param string $user
     * @param string|null $password
     * @return string
     */
    private function filterUserInfo(string $user, null|string $password = null): string
    {
        $userInfo = $user;
        if (null !== $password && '' !== $password) {
            $userInfo .= ':' . $password;
        }
        return $userInfo;
    }

    /**
     * @param string $host
     * @return string
     */
    private function filterHost(string $host): string
    {
        return strtolower($host);
    }

    /**
     * @param int|null $port
     * @return int|null
     * @throws InvalidArgumentException
     */
    private function filterPort(null|int $port): null|int
    {
        if (null === $port) {
            return null;
        }

        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException(sprintf('Invalid port: %d. Must be between 1 and 65535.', $port));
        }

        return $port;
    }

    /**
     * @param string $path
     * @return string
     */
    private function filterPath(string $path): string
    {
        // Don't encode /
        $path = preg_replace_callback(
            '/(?:[^a-zA-Z0-9_\-\.~!\$&\'\(\)\*\+,;=:@\/%]+|%(?![A-Fa-f0-9]{2}))/',
            fn(array $matches): string => rawurlencode($matches[0]),
            $path,
        );
        return $path === '' || str_starts_with($path, '/') ? $path : '/' . $path;
    }

    /**
     * @param string $query
     * @return string
     */
    private function filterQuery(string $query): string
    {
        // Don't encode ? or /
        $query = preg_replace_callback(
            '/(?:[^a-zA-Z0-9_\-\.~!\$&\'\(\)\*\+,;=:@\/\?%]+|%(?![A-Fa-f0-9]{2}))/',
            fn(array $matches): string => rawurlencode($matches[0]),
            $query,
        );
        return ltrim($query, '?');
    }

    /**
     * @param string $fragment
     * @return string
     */
    private function filterFragment(string $fragment): string
    {
        // Don't encode # or / or ?
        $fragment = preg_replace_callback(
            '/(?:[^a-zA-Z0-9_\-\.~!\$&\'\(\)\*\+,;=:@\/\?%]+|%(?![A-Fa-f0-9]{2}))/',
            fn(array $matches): string => rawurlencode($matches[0]),
            $fragment,
        );
        return ltrim($fragment, '#');
    }

    public function __toString(): string
    {
        $uri = '';

        if ('' !== $this->scheme) {
            $uri .= $this->scheme . ':';
        }

        $authority = $this->getAuthority();
        if ('' !== $authority || 'file' === $this->scheme) {
            $uri .= '//' . $authority;
        }

        $path = $this->path;
        if ('' !== $authority && $path !== '' && !str_starts_with($path, '/')) {
            $path = '/' . $path; // Path must be prefixed with / if authority is present
        } elseif ('' === $authority && str_starts_with($path, '//')) {
            $path = '/' . ltrim($path, '/'); // Path must not start with // if no authority
        }

        if ($path === '' && '' !== $authority) {
            $path = '/'; // Add root path if authority is present but path is empty
        }

        $uri .= $path;

        if ('' !== $this->query) {
            $uri .= '?' . $this->query;
        }

        if ('' !== $this->fragment) {
            $uri .= '#' . $this->fragment;
        }

        return $uri;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getAuthority(): string
    {
        $authority = $this->host;
        if ('' === $authority) {
            return '';
        }

        if ('' !== $this->userInfo) {
            $authority = $this->userInfo . '@' . $authority;
        }

        $port = $this->getPort(); // This now correctly returns null for standard ports
        if (null !== $port) {
            $authority .= ':' . $port;
        }

        return $authority;
    }

    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): null|int
    {
        if (null === $this->port) {
            return null;
        }

        // Return null if port is standard for the scheme
        if (isset(self::STANDARD_PORTS[$this->scheme]) && $this->port === self::STANDARD_PORTS[$this->scheme]) {
            return null;
        }

        return $this->port;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    public function withScheme(string $scheme): UriInterface
    {
        $new = clone $this;
        $new->scheme = $this->filterScheme($scheme);
        // Re-validate port against new scheme
        $new->port = $this->filterPort($this->port);
        return $new;
    }

    public function withUserInfo(string $user, null|string $password = null): UriInterface
    {
        $new = clone $this;
        $new->userInfo = $this->filterUserInfo($user, $password);
        return $new;
    }

    public function withHost(string $host): UriInterface
    {
        if ($host === $this->host) {
            return clone $this;
        }
        $new = clone $this;
        $new->host = $this->filterHost($host);
        return $new;
    }

    public function withPort(null|int $port): UriInterface
    {
        $new = clone $this;
        $new->port = $this->filterPort($port);
        return $new;
    }

    public function withPath(string $path): UriInterface
    {
        if ($path === $this->path) {
            return clone $this;
        }
        $new = clone $this;
        $new->path = $this->filterPath($path);
        return $new;
    }

    public function withQuery(string $query): UriInterface
    {
        if ($query === $this->query) {
            return clone $this;
        }
        $new = clone $this;
        $new->query = $this->filterQuery($query);
        return $new;
    }

    public function withFragment(string $fragment): UriInterface
    {
        if ($fragment === $this->fragment) {
            return clone $this;
        }
        $new = clone $this;
        $new->fragment = $this->filterFragment($fragment);
        return $new;
    }
}
