<?php

declare(strict_types=1);

namespace Waffle\Commons\Http;

use InvalidArgumentException;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * PSR-7 ServerRequestInterface implementation.
 *
 * This class represents a server-side HTTP request.
 * It is "immutable", meaning any change (via "with..." methods)
 * results in a new object (a clone) with the modified data.
 */
class ServerRequest implements ServerRequestInterface
{
    /**
     * @var array<string, string[]> Stores headers with their original case.
     */
    private array $headers = [];

    /**
     * @var array<string, string> Map of normalized (lowercase) header names to their original case.
     */
    private array $normalizedHeaders = [];

    /**
     * @param string $method HTTP method (GET, POST, etc.)
     * @param UriInterface $uri The request URI object.
     * @param array<string, string|string[]> $headers Request headers.
     * @param StreamInterface $body Request body.
     * @param string $protocolVersion Protocol version (e.g., "1.1").
     * @param array<string, mixed> $serverParams Data from $_SERVER.
     * @param array<string, mixed> $cookieParams Data from $_COOKIE.
     * @param array<string, mixed> $queryParams Data from $_GET.
     * @param array<mixed> $uploadedFiles Data from $_FILES (UploadedFileInterface objects).
     * @param mixed $parsedBody Data from $_POST or decoded JSON.
     * @param array<string, mixed> $attributes Derived attributes (e.g., route parameters).
     */
    public function __construct(
        private string $method,
        private UriInterface $uri,
        array $headers,
        private StreamInterface $body,
        private string $protocolVersion = '1.1',
        private array $serverParams = [],
        private array $cookieParams = [],
        private array $queryParams = [],
        private array $uploadedFiles = [],
        private mixed $parsedBody = null,
        private array $attributes = [],
        private null|string $requestTarget = null,
    ) {
        $this->processHeaders($headers);
    }

    /**
     * Private method to normalize and store headers.
     * @param array<string, string|string[]> $headers
     */
    private function processHeaders(array $headers): void
    {
        $this->headers = [];
        $this->normalizedHeaders = [];
        foreach ($headers as $name => $values) {
            $this->validateHeaderName($name);
            $values = $this->normalizeHeaderValues($values);
            $normalized = strtolower($name);

            // Store the original case for mapping
            $this->normalizedHeaders[$normalized] = $name;
            // Store values under the original case name
            $this->headers[$name] = $values;
        }
    }

    /**
     * Validates a header name.
     */
    private function validateHeaderName(mixed $name): void
    {
        if (!is_string($name) || !preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/', $name)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid header name: "%s"',
                is_string($name) ? $name : gettype($name),
            ));
        }
    }

    /**
     * Ensures header values are an array of strings.
     * @param mixed $values
     * @return string[]
     */
    private function normalizeHeaderValues(mixed $values): array
    {
        $values = is_array($values) ? $values : [$values];
        foreach ($values as $value) {
            if (
                !is_string($value) && !is_numeric($value)
                || preg_match("#(?:(?:(?<!\r)\n)|(?:\r(?!\n))|(?:\r\n(?![ \t])))#", (string) $value)
            ) {
                throw new InvalidArgumentException('Invalid header value.');
            }
        }
        return array_map('strval', $values);
    }

    /**
     * Creates a clone. Helper method for immutability.
     */
    private function cloneWith(): static
    {
        return clone $this;
    }

    // --- MessageInterface Implementation ---

    #[\Override]
    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    #[\Override]
    public function withProtocolVersion(string $version): MessageInterface
    {
        $new = $this->cloneWith();
        $new->protocolVersion = $version;
        return $new;
    }

    #[\Override]
    public function getHeaders(): array
    {
        return $this->headers;
    }

    #[\Override]
    public function hasHeader(string $name): bool
    {
        return isset($this->normalizedHeaders[strtolower($name)]);
    }

    #[\Override]
    public function getHeader(string $name): array
    {
        $normalized = strtolower($name);
        if (!isset($this->normalizedHeaders[$normalized])) {
            return [];
        }

        $originalName = $this->normalizedHeaders[$normalized];
        return $this->headers[$originalName];
    }

    #[\Override]
    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    #[\Override]
    public function withHeader(string $name, $value): MessageInterface
    {
        $this->validateHeaderName($name);
        $value = $this->normalizeHeaderValues($value);
        $normalized = strtolower($name);

        $new = $this->cloneWith();

        // Remove old case-insensitive mapping if it exists
        if (isset($new->normalizedHeaders[$normalized])) {
            unset($new->headers[$new->normalizedHeaders[$normalized]]);
        }

        // Add the new mapping
        $new->headers[$name] = $value;
        $new->normalizedHeaders[$normalized] = $name;

        return $new;
    }

    #[\Override]
    public function withAddedHeader(string $name, $value): MessageInterface
    {
        $this->validateHeaderName($name);
        $value = $this->normalizeHeaderValues($value);
        $normalized = strtolower($name);

        // If the header does not exist, this is like withHeader
        if (!isset($this->normalizedHeaders[$normalized])) {
            $new = $this->cloneWith();
            $new->headers[$name] = $value;
            $new->normalizedHeaders[$normalized] = $name;
            return $new;
        }

        // If the header exists, merge the values
        $new = $this->cloneWith();
        $originalName = $this->normalizedHeaders[$normalized];
        $new->headers[$originalName] = array_merge($this->headers[$originalName], $value);

        return $new;
    }

    #[\Override]
    public function withoutHeader(string $name): MessageInterface
    {
        $normalized = strtolower($name);

        if (!isset($this->normalizedHeaders[$normalized])) {
            // Nothing to do, return the current instance (immutability)
            return $this;
        }

        $new = $this->cloneWith();
        $originalName = $this->normalizedHeaders[$normalized];

        unset($new->headers[$originalName]);
        unset($new->normalizedHeaders[$normalized]);

        return $new;
    }

    #[\Override]
    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    #[\Override]
    public function withBody(StreamInterface $body): MessageInterface
    {
        $new = $this->cloneWith();
        $new->body = $body;
        return $new;
    }

    // --- RequestInterface Implementation ---

    #[\Override]
    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== null) {
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

    #[\Override]
    public function withRequestTarget(string $requestTarget): RequestInterface
    {
        if (preg_match('#\s#', $requestTarget)) {
            throw new InvalidArgumentException('Request target cannot contain spaces.');
        }

        $new = $this->cloneWith();
        $new->requestTarget = $requestTarget;
        return $new;
    }

    #[\Override]
    public function getMethod(): string
    {
        return $this->method;
    }

    #[\Override]
    public function withMethod(string $method): RequestInterface
    {
        if (!preg_match('/^[!#$%&\'*+.^_`|~0-9a-zA-Z-]+$/', $method)) {
            throw new InvalidArgumentException(sprintf('Invalid HTTP method: "%s"', $method));
        }

        $new = $this->cloneWith();
        $new->method = $method;
        return $new;
    }

    #[\Override]
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    #[\Override]
    public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface
    {
        $new = $this->cloneWith();
        $new->uri = $uri;

        if ($preserveHost && $this->hasHeader('Host')) {
            // Host is preserved, nothing more to do.
            return $new;
        }

        // The host must be updated from the new URI.
        $host = $uri->getHost();
        if ($host !== '') {
            if (($port = $uri->getPort()) !== null) {
                $host .= ':' . $port;
            }
            // Use withHeader to correctly update normalized headers
            return $new->withHeader('Host', $host);
        }

        // If the new URI has no host, do not update the Host header.
        return $new;
    }

    // --- ServerRequestInterface Implementation ---

    #[\Override]
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    #[\Override]
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    #[\Override]
    public function withCookieParams(array $cookies): ServerRequestInterface
    {
        $new = $this->cloneWith();
        $new->cookieParams = $cookies;
        return $new;
    }

    #[\Override]
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    #[\Override]
    public function withQueryParams(array $query): ServerRequestInterface
    {
        $new = $this->cloneWith();
        $new->queryParams = $query;
        return $new;
    }

    #[\Override]
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    #[\Override]
    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
    {
        // A real implementation would validate that $uploadedFiles is a tree
        // of UploadedFileInterface objects.
        $new = $this->cloneWith();
        $new->uploadedFiles = $uploadedFiles;
        return $new;
    }

    #[\Override]
    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    #[\Override]
    public function withParsedBody($data): ServerRequestInterface
    {
        // A real implementation would validate the type of $data
        // (null, object, or array).
        $new = $this->cloneWith();
        $new->parsedBody = $data;
        return $new;
    }

    #[\Override]
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    #[\Override]
    public function getAttribute(string $name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }

    #[\Override]
    public function withAttribute(string $name, $value): ServerRequestInterface
    {
        $new = $this->cloneWith();
        $new->attributes[$name] = $value;
        return $new;
    }

    #[\Override]
    public function withoutAttribute(string $name): ServerRequestInterface
    {
        if (!isset($this->attributes[$name])) {
            return $this;
        }

        $new = $this->cloneWith();
        unset($new->attributes[$name]);
        return $new;
    }
}
