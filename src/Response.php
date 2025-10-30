<?php

declare(strict_types=1);

namespace Waffle\Commons\Http;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Waffle\Commons\Http\Abstract\AbstractMessage;

class Response extends AbstractMessage implements ResponseInterface
{
    private int $statusCode = 200;
    private string $reasonPhrase = 'OK';

    private const REASON_PHRASES = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
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
        418 => 'I\'m a teapot',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
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
     * @param array $headers
     * @param StreamInterface|resource|string|null $body
     * @param string $version
     * @param string|null $reasonPhrase
     */
    public function __construct(
        int $statusCode = 200,
        array $headers = [],
        $body = null,
        string $version = '1.1',
        null|string $reasonPhrase = null,
    ) {
        $this->validateStatusCode($statusCode);
        $this->statusCode = $statusCode;
        $this->protocolVersion = $version;
        $this->headers = $this->normalizeHeaders($headers);
        $this->body = $this->createStreamBody($body);
        $this->reasonPhrase = $reasonPhrase ?? self::REASON_PHRASES[$statusCode] ?? 'Unknown';
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
                throw new RuntimeException('Failed to open php://temp stream.');
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

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
    {
        $this->validateStatusCode($code);

        $new = clone $this;
        $new->statusCode = $code;
        $new->reasonPhrase = $reasonPhrase === '' ? self::REASON_PHRASES[$code] ?? 'Unknown' : $reasonPhrase;
        return $new;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    private function validateStatusCode(int $code): void
    {
        if ($code < 100 || $code > 599) {
            throw new InvalidArgumentException(sprintf(
                'Invalid status code "%d"; must be an integer between 100 and 599.',
                $code,
            ));
        }
    }
}
