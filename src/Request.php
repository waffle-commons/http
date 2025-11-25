<?php

declare(strict_types=1);

namespace Waffle\Commons\Http;

use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Waffle\Commons\Http\Abstract\AbstractMessage;

/**
 * PSR-7 Request implementation (Client-side).
 */
class Request extends AbstractMessage implements RequestInterface
{
    private string $method;
    private UriInterface $uri;
    private null|string $requestTarget = null;

    /**
     * @param string $method HTTP method.
     * @param UriInterface $uri URI.
     */
    public function __construct(string $method, UriInterface $uri)
    {
        $this->method = $method;
        $this->uri = $uri;
        $this->body = new Stream('php://temp', 'r+'); // Default empty body

        // PSR-7: During construction, implementations MUST attempt to set the Host header from a provided URI
        if ($uri->getHost()) {
            $this->updateHostFromUri($uri);
        }
    }

    /**
     * {@inheritdoc}
     */
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

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function withRequestTarget(string $requestTarget): RequestInterface
    {
        // PSR-7 3.2.1: Request target must not contain whitespace
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
    public function withMethod(string $method): RequestInterface
    {
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
    public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface
    {
        $new = clone $this;
        $new->uri = $uri;

        if ($preserveHost && $this->hasHeader('Host')) {
            return $new;
        }

        if (!$uri->getHost()) {
            return $new;
        }

        $new->updateHostFromUri($uri);

        return $new;
    }

    /**
     * Updates the Host header from the URI.
     */
    private function updateHostFromUri(UriInterface $uri): void
    {
        $host = $uri->getHost();
        if ($uri->getPort()) {
            $host .= ':' . $uri->getPort();
        }

        // We set the header directly in the headers array to handle normalization correctly
        // using the logic from AbstractMessage (but accessing protected properties is cleaner via withHeader)

        // However, since we are inside the object, let's use the existing infrastructure:
        // We can't use $this->withHeader() easily in constructor without cloning.
        // So we replicate logic or modify state directly since we are initializing/mutating clone.

        $headerName = 'Host';
        $normalizedName = 'host';

        // Remove existing
        if (isset($this->headerNames[$normalizedName])) {
            unset($this->headers[$this->headerNames[$normalizedName]]);
        }

        $this->headerNames[$normalizedName] = $headerName;
        $this->headers[$headerName] = [$host];
    }
}
