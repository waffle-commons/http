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
class Uri implements UriInterface
{
    private string $scheme = '';
    private string $userInfo = '';
    private string $host = '';
    private null|int $port = null;
    private string $path = '';
    private string $query = '';
    private string $fragment = '';

    public function __construct(
        string $scheme = '',
        string $host = '',
        null|int $port = null,
        string $path = '/',
        string $query = '',
        string $fragment = '',
        string $userInfo = '',
    ) {
        $this->scheme = $this->filterScheme($scheme);
        $this->host = $this->filterHost($host);
        $this->port = $this->filterPort($port);
        $this->path = $this->filterPath($path);
        $this->query = $this->filterQuery($query);
        $this->fragment = $this->filterFragment($fragment);
        $this->userInfo = $this->filterUserInfo($userInfo);
    }

    // --- Filter methods ---

    private function filterScheme(string $scheme): string
    {
        return strtolower(preg_replace('/([^a-zA-Z0-9+.-]+)/', '', $scheme));
    }

    private function filterHost(string $host): string
    {
        return strtolower($host);
    }

    private function filterPort(null|int $port): null|int
    {
        if ($port === null) {
            return null;
        }
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException(sprintf('Invalid port: %d. Must be between 1 and 65535.', $port));
        }
        return $port;
    }

    private function filterPath(string $path): string
    {
        return preg_replace_callback(
            '/(?:[^a-zA-Z0-9_\-.~!$&\'()*+,;=:@%]+|%(?![A-Fa-f0-9]{2}))/',
            fn(array $matches) => rawurlencode($matches[0]),
            $path,
        );
    }

    private function filterQuery(string $query): string
    {
        return preg_replace_callback(
            '/(?:[^a-zA-Z0-9_\-.~!$&\'()*+,;=:@%?\/]+|%(?![A-Fa-f0-9]{2}))/',
            fn(array $matches) => rawurlencode($matches[0]),
            $query,
        );
    }

    private function filterFragment(string $fragment): string
    {
        return $this->filterQuery($fragment); // Same rules as query
    }

    private function filterUserInfo(string $userInfo): string
    {
        return preg_replace_callback(
            '/(?:[^a-zA-Z0-9_\-.~!$&\'()*+,;=:]+|%(?![A-Fa-f0-9]{2}))/',
            fn(array $matches) => rawurlencode($matches[0]),
            $userInfo,
        );
    }

    // --- Interface methods ---

    #[\Override]
    public function getScheme(): string
    {
        return $this->scheme;
    }

    #[\Override]
    public function getAuthority(): string
    {
        if ($this->host === '') {
            return '';
        }
        $authority = $this->host;
        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }
        if ($this->port !== null && $this->port !== $this->getDefaultPort()) {
            $authority .= ':' . $this->port;
        }
        return $authority;
    }

    #[\Override]
    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    #[\Override]
    public function getHost(): string
    {
        return $this->host;
    }

    #[\Override]
    public function getPort(): null|int
    {
        if ($this->port === null) {
            return $this->getDefaultPort();
        }
        return $this->port;
    }

    private function getDefaultPort(): null|int
    {
        if ($this->scheme === 'http') {
            return 80;
        }
        if ($this->scheme === 'https') {
            return 443;
        }
        return null;
    }

    #[\Override]
    public function getPath(): string
    {
        return $this->path ?: '/';
    }

    #[\Override]
    public function getQuery(): string
    {
        return $this->query;
    }

    #[\Override]
    public function getFragment(): string
    {
        return $this->fragment;
    }

    #[\Override]
    public function withScheme(string $scheme): UriInterface
    {
        $new = clone $this;
        $new->scheme = $this->filterScheme($scheme);
        return $new;
    }

    #[\Override]
    public function withUserInfo(string $user, null|string $password = null): UriInterface
    {
        $new = clone $this;
        $info = $user;
        if ($password !== null && $password !== '') {
            $info .= ':' . $password;
        }
        $new->userInfo = $this->filterUserInfo($info);
        return $new;
    }

    #[\Override]
    public function withHost(string $host): UriInterface
    {
        $new = clone $this;
        $new->host = $this->filterHost($host);
        return $new;
    }

    #[\Override]
    public function withPort(null|int $port): UriInterface
    {
        $new = clone $this;
        $new->port = $this->filterPort($port);
        return $new;
    }

    #[\Override]
    public function withPath(string $path): UriInterface
    {
        $new = clone $this;
        $new->path = $this->filterPath($path);
        return $new;
    }

    #[\Override]
    public function withQuery(string $query): UriInterface
    {
        $new = clone $this;
        $new->query = $this->filterQuery($query);
        return $new;
    }

    #[\Override]
    public function withFragment(string $fragment): UriInterface
    {
        $new = clone $this;
        $new->fragment = $this->filterFragment($fragment);
        return $new;
    }

    #[\Override]
    public function __toString(): string
    {
        $uri = '';
        if ($this->scheme !== '') {
            $uri .= $this->scheme . ':';
        }
        if (($authority = $this->getAuthority()) !== '') {
            $uri .= '//' . $authority;
        }
        $path = $this->getPath();
        if ($authority !== '' && $path !== '' && $path[0] !== '/') {
            $path = '/' . $path;
        }
        $uri .= $path;
        if ($this->query !== '') {
            $uri .= '?' . $this->query;
        }
        if ($this->fragment !== '') {
            $uri .= '#' . $this->fragment;
        }
        return $uri;
    }
}
