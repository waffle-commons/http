<?php

declare(strict_types=1);

namespace Waffle\Commons\Http;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Waffle\Commons\Http\Abstract\AbstractMessage;

/**
 * PSR-7 ServerRequestInterface implementation.
 *
 * @see https://www.php-fig.org/psr/psr-7/#321-psrhttpmessageserverrequestinterface
 */
class ServerRequest extends AbstractMessage implements ServerRequestInterface
{
    private array $attributes = [];
    private array $cookieParams = [];
    /** @var null|array|object */
    private $parsedBody = null;
    private array $queryParams = [];
    private array $serverParams = [];
    private array $uploadedFiles = [];
    private null|string $requestTarget = null;
    private string $method;
    private UriInterface $uri;

    /**
     * @param string $method HTTP method.
     * @param UriInterface $uri URI instance.
     * @param array $headers Request headers.
     * @param StreamInterface|resource|string|null $body Request body.
     * @param string $version Protocol version.
     * @param array $serverParams SAPI parameters (typically $_SERVER).
     * @param array $cookieParams Cookies (typically $_COOKIE).
     * @param array $queryParams Query parameters (typically $_GET).
     * @param array|object|null $parsedBody Parsed body (typically $_POST or decoded JSON).
     * @param array $uploadedFiles Uploaded files (typically $_FILES).
     */
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
        array $uploadedFiles = [],
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
     * Creates a Stream instance for the request body.
     *
     * @param StreamInterface|resource|string|null $body
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

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getRequestTarget(): string
    {
        if (null !== $this->requestTarget) {
            return $this->requestTarget;
        }

        // Builds target from URI if not set
        $target = $this->uri->getPath();
        if ($target === '') {
            $target = '/';
        }
        if ($this->uri->getQuery() !== '') {
            $target .= '?' . $this->uri->getQuery();
        }

        return $target;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function withRequestTarget(string $requestTarget): ServerRequestInterface
    {
        // Validates that there is no whitespace (PSR-7 section 3.2.1)
        if (preg_match('/\s/', $requestTarget)) {
            throw new InvalidArgumentException('Invalid request target provided; must not contain whitespace.');
        }
        $new = clone $this;
        $new->requestTarget = $requestTarget;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function withMethod(string $method): ServerRequestInterface
    {
        if ($method === $this->method) {
            return clone $this;
        }
        // NOTE: Method validation (e.g., RFC 7230 token format) could be added here.
        $new = clone $this;
        $new->method = $method;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function withUri(UriInterface $uri, bool $preserveHost = false): ServerRequestInterface
    {
        $new = clone $this;
        $new->uri = $uri;

        if ($preserveHost && $this->hasHeader('Host')) {
            // Host header is preserved, do nothing more.
            return $new;
        }

        // Updates Host header from new URI
        $host = $uri->getHost();
        if ($host !== '') {
            $port = $uri->getPort();
            if (null !== $port) {
                $host .= ':' . $port;
            }
            // Use withHeader to replace existing Host header
            // (case logic is handled in AbstractMessage)
            return $new->withHeader('Host', $host);
        }

        // If the new URI has no host, don't set the Host header.
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function withCookieParams(array $cookies): ServerRequestInterface
    {
        $new = clone $this;
        $new->cookieParams = $cookies;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function withQueryParams(array $query): ServerRequestInterface
    {
        $new = clone $this;
        $new->queryParams = $query;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
    {
        // NOTE: Deep validation to ensure array only contains
        // UploadedFileInterface instances could be added here.
        $new = clone $this;
        $new->uploadedFiles = $uploadedFiles;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function withParsedBody($data): ServerRequestInterface
    {
        // Validates $data type according to PSR-7
        if (!is_array($data) && !is_object($data) && null !== $data) {
            throw new InvalidArgumentException('Parsed body must be an array, object, or null.');
        }
        $new = clone $this;
        $new->parsedBody = $data;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getAttribute(string $name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function withAttribute(string $name, $value): ServerRequestInterface
    {
        $new = clone $this;
        $new->attributes[$name] = $value;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function withoutAttribute(string $name): ServerRequestInterface
    {
        if (!array_key_exists($name, $this->attributes)) {
            return clone $this; // No need to clone if attribute doesn't exist
        }
        $new = clone $this;
        unset($new->attributes[$name]);
        return $new;
    }
}
