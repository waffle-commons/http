<?php

declare(strict_types=1);

namespace Waffle\Commons\Http\Abstract;

use InvalidArgumentException;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Abstract implementation of PSR-7 MessageInterface.
 *
 * Provides the common logic for managing protocol version, headers, and body,
 * as shared by Request, ServerRequest, and Response.
 */
abstract class AbstractMessage implements MessageInterface
{
    /** @var string $protocolVersion Protocol version. */
    protected string $protocolVersion = '1.1';

    /**
     * @var array<string, string> Map of normalized header names to their original casing.
     */
    protected array $headerNames = [];

    /**
     * @var array<string, string[]> Map of original header names to an array of values.
     */
    protected array $headers = [];

    /** @var StreamInterface $body Message body. */
    protected StreamInterface $body;

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function withProtocolVersion(string $version): MessageInterface
    {
        if (!in_array($version, ['1.0', '1.1', '2.0', '2', '3.0'], strict: true)) {
            throw new InvalidArgumentException(sprintf('Unsupported protocol version "%s".', $version));
        }
        if ($this->protocolVersion === $version) {
            return $this;
        }
        $new = clone $this;
        $new->protocolVersion = $version;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function hasHeader(string $name): bool
    {
        return array_key_exists(strtolower($name), $this->headerNames);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getHeader(string $name): array
    {
        $normalizedName = $this->headerNames[strtolower($name)] ?? null;

        if ($normalizedName === null) {
            return [];
        }

        return $this->headers[$normalizedName] ?? [];
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function withHeader(string $name, $value): MessageInterface
    {
        $this->validateHeaderName($name);
        $value = $this->normalizeHeaderValue($value);
        $normalizedName = strtolower($name);

        $new = clone $this;
        // If the header exists (even with different case), remove it first
        if ($new->hasHeader($name)) {
            unset($new->headers[$new->headerNames[$normalizedName]]);
        }

        // Store the new case and value
        $new->headerNames[$normalizedName] = $name;
        $new->headers[$name] = $value;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function withAddedHeader(string $name, $value): MessageInterface
    {
        $this->validateHeaderName($name);
        $value = $this->normalizeHeaderValue($value);
        $normalizedName = strtolower($name);

        $new = clone $this;
        if (!$new->hasHeader($name)) {
            // Simple add if header does not exist
            $new->headerNames[$normalizedName] = $name;
            $new->headers[$name] = $value;
            return $new;
        }

        // Merge if header already exists
        $originalName = (string) ($this->headerNames[$normalizedName] ?? '');
        $new->headers[$originalName] = array_merge($this->headers[$originalName] ?? [], $value);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function withoutHeader(string $name): MessageInterface
    {
        $normalizedName = strtolower($name);
        if (!array_key_exists($normalizedName, $this->headerNames)) {
            return $this; // No change, return same instance
        }

        // Remove header from both maps
        $originalName = $this->headerNames[$normalizedName];
        $new = clone $this;
        unset($new->headers[$originalName], $new->headerNames[$normalizedName]);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function withBody(StreamInterface $body): MessageInterface
    {
        if ($body === $this->body) {
            return $this; // No change, return same instance
        }
        $new = clone $this;
        $new->body = $body;
        return $new;
    }

    /**
     * Validates a header name.
     *
     * @param string $name
     * @throws InvalidArgumentException
     */
    protected function validateHeaderName(string $name): void
    {
        // Complies with RFC 7230, section 3.2.
        if (1 !== preg_match('/^[a-zA-Z0-9\'`#$%&*+.^~_|-]+$/', $name)) {
            throw new InvalidArgumentException(sprintf('Invalid header name "%s".', $name));
        }
    }

    /**
     * Normalizes a header value to an array of strings.
     *
     * @param string|string[] $value
     * @return string[]
     * @throws InvalidArgumentException
     */
    protected function normalizeHeaderValue($value): array
    {
        if (!is_array($value)) {
            $value = [$value];
        }

        if ($value === []) {
            throw new InvalidArgumentException('Header value must not be an empty array.');
        }

        $normalized = [];
        foreach ($value as $v) {
            // Validates that each value is scalar or null

            // Validates header value characters (RFC 7230, section 3.2)
            if (1 !== preg_match('/^[ \t\x21-\x7E\x80-\xFF]*$/', $v)) {
                throw new InvalidArgumentException(sprintf('Invalid header value: "%s".', $v));
            }
            $normalized[] = $v;
        }

        return $normalized;
    }

    /**
     * Normalizes an array of headers.
     *
     * @param array $headers Associative array of headers.
     * @return array<string, string[]> Normalized array of headers.
     */
    protected function normalizeHeaders(array $headers): array
    {
        $normalizedHeaders = [];
        $this->headerNames = [];

        foreach ($headers as $name => $value) {
            if (!is_string($name)) {
                throw new InvalidArgumentException(sprintf(
                    'Header name must be a string, %s provided.',
                    gettype($name),
                ));
            }
            $this->validateHeaderName($name);
            $value = $this->normalizeHeaderValue($value);
            $normalizedName = strtolower($name);

            // Stores the original case and values
            $this->headerNames[$normalizedName] = $name;
            $normalizedHeaders[$name] = $value;
        }

        return $normalizedHeaders;
    }
}
