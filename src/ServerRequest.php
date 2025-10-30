<?php

declare(strict_types=1);

namespace Waffle\Commons\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use InvalidArgumentException;
use Waffle\Commons\Http\Abstract\AbstractMessage;

class ServerRequest extends AbstractMessage implements ServerRequestInterface
{
    private array $attributes = [];
    private array $cookieParams = [];
    private ?array $parsedBody = null;
    private array $queryParams = [];
    private array $serverParams = [];
    private array $uploadedFiles = [];
    private ?string $requestTarget = null;
    private string $method;
    private UriInterface $uri;

    public function __construct(
        string $method,
        UriInterface $uri,
        array $headers = [],
        $body = null,
        string $version = '1.1',
        array $serverParams = [],
        array $cookieParams = [],
        array $queryParams = [],
        $parsedBody = null,
        array $uploadedFiles = []
    ) {
        $this->method = $method;
        $this->uri = $uri;
        $this->headers = $this->normalizeHeaders($headers);
        $this->body = $this->createStreamBody($body);
        $this->protocolVersion = $version;
        $this->serverParams = $serverParams;
        $this->cookieParams = $cookieParams;
        $this->queryParams = $queryParams;
        $this->parsedBody = $parsedBody;
        $this->uploadedFiles = $uploadedFiles;
    }

    /**
     * @param $body
     * @return StreamInterface
     */
    private function createStreamBody($body): StreamInterface
    {
        if ($body instanceof StreamInterface) {
            return $body;
        }

        if (is_string($body) || null === $body) {
            $resource = fopen('php://temp', 'r+');
            if (false === $resource) {
                throw new \RuntimeException('Failed to open php://temp stream.');
            }
            if (is_string($body) && '' !== $body) {
                fwrite($resource, $body);
                fseek($resource, 0);
            }
            return new Stream($resource);
        }

        if (is_resource($body)) {
            return new Stream($body);
        }

        throw new InvalidArgumentException('Invalid body type; must be string, resource, null, or StreamInterface.');
    }

    public function getRequestTarget(): string
    {
        if (null !== $this->requestTarget) {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();
        if ($target === '') {
            $target = '/';
        }
        if ($this->uri->getQuery() !== '') {
            $target .= '?' . $this->uri->getQuery();
        }

        return $target;
    }

    public function withRequestTarget(string $requestTarget): ServerRequestInterface
    {
        if (preg_match('/\s/', $requestTarget)) {
            throw new InvalidArgumentException('Invalid request target provided; must not contain whitespace.');
        }
        $new = clone $this;
        $new->requestTarget = $requestTarget;
        return $new;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod(string $method): ServerRequestInterface
    {
        if ($method === $this->method) {
            return clone $this;
        }
        $new = clone $this;
        $new->method = $method;
        return $new;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): ServerRequestInterface
    {
        $new = clone $this;
        $new->uri = $uri;

        if ($preserveHost && $this->hasHeader('Host')) {
            // Host header is preserved
            return $new;
        }

        // Update Host header from new URI
        $host = $uri->getHost();
        if ($host !== '') {
            $port = $uri->getPort();
            if (null !== $port) {
                $host .= ':' . $port;
            }
            // Use withHeader to maintain case-insensitivity logic
            return $new->withHeader('Host', $host);
        }

        return $new;
    }

    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    public function withCookieParams(array $cookies): ServerRequestInterface
    {
        $new = clone $this;
        $new->cookieParams = $cookies;
        return $new;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function withQueryParams(array $query): ServerRequestInterface
    {
        $new = clone $this;
        $new->queryParams = $query;
        return $new;
    }

    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
    {
        $new = clone $this;
        $new->uploadedFiles = $uploadedFiles;
        return $new;
    }

    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    public function withParsedBody($data): ServerRequestInterface
    {
        $new = clone $this;
        $new->parsedBody = $data;
        return $new;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute(string $name, $value): ServerRequestInterface
    {
        $new = clone $this;
        $new->attributes[$name] = $value;
        return $new;
    }

    public function withoutAttribute(string $name): ServerRequestInterface
    {
        if (!array_key_exists($name, $this->attributes)) {
            return clone $this;
        }
        $new = clone $this;
        unset($new->attributes[$name]);
        return $new;
    }
}
