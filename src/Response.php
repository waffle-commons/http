<?php

declare(strict_types=1);

namespace Waffle\Commons\Http;

use InvalidArgumentException;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * A concrete implementation of PSR-7 ResponseInterface.
 *
 * This class represents an outgoing HTTP response.
 * It follows immutability principles; all `with...` methods
 * return a new instance.
 */
class Response implements ResponseInterface
{
    /** @var array<string, string[]> Map of normalized header names to original names and values */
    private array $headers = [];

    /** @var array<string, string> Map of lower-case header names to original names */
    private array $headerNames = [];

    private string $protocolVersion;
    private StreamInterface $body;
    private int $statusCode;
    private string $reasonPhrase;

    /**
     * A map of standard HTTP status codes to their reason phrases.
     *
     * @var array<int, string>
     */
    private const REASON_PHRASES = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];

    /**
     * @param int $statusCode
     * @param array<string, string|string[]> $headers
     * @param StreamInterface|resource|string|null $body
     * @param string $protocolVersion
     * @param string|null $reasonPhrase
     */
    public function __construct(
        int $statusCode = 200,
        array $headers = [],
        $body = null,
        string $protocolVersion = '1.1',
        null|string $reasonPhrase = null,
    ) {
        $this->statusCode = $statusCode;
        $this->protocolVersion = $protocolVersion;
        $this->setHeaders($headers);
        $this->reasonPhrase = $reasonPhrase ?? self::REASON_PHRASES[$this->statusCode] ?? '';

        if ($body instanceof StreamInterface) {
            $this->body = $body;
        } elseif ($body === null) {
            $this->body = new Stream('php://temp', 'wb+');
        } else {
            // String or resource
            $this->body = new Stream('php://temp', 'wb+');
            $this->body->write((string) $body);
            $this->body->rewind();
        }
    }

    // --- ResponseInterface Methods ---

    #[\Override]
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    #[\Override]
    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
    {
        if ($this->statusCode === $code && ($reasonPhrase === '' || $this->reasonPhrase === $reasonPhrase)) {
            return $this;
        }

        $clone = clone $this;
        $clone->statusCode = $code;
        $clone->reasonPhrase = $reasonPhrase ?: self::REASON_PHRASES[$code] ?? '';
        return $clone;
    }

    #[\Override]
    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    // --- MessageInterface Methods ---

    #[\Override]
    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    #[\Override]
    public function withProtocolVersion(string $version): MessageInterface
    {
        if ($this->protocolVersion === $version) {
            return $this;
        }

        $clone = clone $this;
        $clone->protocolVersion = $version;
        return $clone;
    }

    #[\Override]
    public function getHeaders(): array
    {
        return $this->headers;
    }

    #[\Override]
    public function hasHeader(string $name): bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    #[\Override]
    public function getHeader(string $name): array
    {
        $normalizedName = strtolower($name);
        if (!isset($this->headerNames[$normalizedName])) {
            return [];
        }

        $originalName = $this->headerNames[$normalizedName];
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
        $normalizedName = strtolower($name);
        $clone = clone $this;

        // Remove existing header (case-insensitive)
        if (isset($clone->headerNames[$normalizedName])) {
            $originalName = $clone->headerNames[$normalizedName];
            unset($clone->headers[$originalName]);
        }

        $value = $this->normalizeHeaderValue($value);

        // Set new header
        $clone->headerNames[$normalizedName] = $name;
        $clone->headers[$name] = $value;

        return $clone;
    }

    #[\Override]
    public function withAddedHeader(string $name, $value): MessageInterface
    {
        $normalizedName = strtolower($name);
        $clone = clone $this;

        $value = $this->normalizeHeaderValue($value);

        if (isset($clone->headerNames[$normalizedName])) {
            // Header exists, append
            $originalName = $clone->headerNames[$normalizedName];
            $clone->headers[$originalName] = array_merge($clone->headers[$originalName], $value);
        } else {
            // New header
            $clone->headerNames[$normalizedName] = $name;
            $clone->headers[$name] = $value;
        }

        return $clone;
    }

    #[\Override]
    public function withoutHeader(string $name): MessageInterface
    {
        $normalizedName = strtolower($name);
        if (!isset($this->headerNames[$normalizedName])) {
            return $this; // No change
        }

        $clone = clone $this;
        $originalName = $clone->headerNames[$normalizedName];

        unset($clone->headers[$originalName]);
        unset($clone->headerNames[$normalizedName]);

        return $clone;
    }

    #[\Override]
    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    #[\Override]
    public function withBody(StreamInterface $body): MessageInterface
    {
        if ($this->body === $body) {
            return $this;
        }

        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }

    // --- Private Helper Methods ---

    /**
     * Populates the internal header properties from an array.
     *
     * @param array<string, string|string[]> $headers
     */
    private function setHeaders(array $headers): void
    {
        $this->headers = [];
        $this->headerNames = [];
        foreach ($headers as $name => $value) {
            $value = $this->normalizeHeaderValue($value);
            $normalizedName = strtolower($name);

            $this->headerNames[$normalizedName] = $name;
            $this->headers[$name] = $value;
        }
    }

    /**
     * Ensures the header value is an array of strings.
     *
     * @param string|string[] $value
     * @return string[]
     * @throws InvalidArgumentException
     */
    private function normalizeHeaderValue($value): array
    {
        if (is_string($value)) {
            $value = [$value];
        }

        if (!is_array($value)) {
            throw new InvalidArgumentException('Header value must be a string or an array of strings.');
        }

        foreach ($value as $item) {
            if (!is_string($item)) {
                throw new InvalidArgumentException('Header value must be a string or an array of strings.');
            }
        }

        return $value;
    }
}
