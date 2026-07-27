<?php

declare(strict_types=1);

namespace Waffle\Commons\Http\Factory;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Waffle\Commons\Contracts\Http\GlobalsFactoryInterface;
use Waffle\Commons\Http\ServerRequest;
use Waffle\Commons\Http\Stream;

/**
 * Creates a ServerRequestInterface (PSR-7) instance from PHP superglobals.
 *
 * This factory is specific to the Waffle framework's bootstrap process.
 *
 * Trusted-host enforcement is NOT performed here. Per RFC-003 §3.2 (Alpha 6 P0),
 * Host Header Injection is rejected by `Waffle\Commons\Pipeline\Middleware\TrustedHostMiddleware`
 * which sits between ErrorHandlerMiddleware and CoreRoutingMiddleware in the PSR-15 stack.
 */
class GlobalsFactory implements GlobalsFactoryInterface
{
    /**
     * @var callable(): StreamInterface
     */
    private $bodyStreamFactory;

    private UploadedFilesNormalizer $uploadedFilesNormalizer;

    private ServerRequestHeadersMapper $headersMapper;

    private ServerRequestUriMapper $uriMapper;

    /**
     * @param (callable(): StreamInterface)|null $bodyStreamFactory Factory to create a Stream for php://input.
     * @param UploadedFilesNormalizer|null $uploadedFilesNormalizer Normalizes the $_FILES tree (defaults to a fresh
     *        instance). When given explicitly, it is used as-is — $uploadBaseDir below is ignored, since a
     *        caller-supplied normalizer is assumed to already be fully configured.
     * @param ServerRequestHeadersMapper|null $headersMapper Maps $_SERVER into the PSR-7 header set (defaults to a fresh instance).
     * @param ServerRequestUriMapper|null $uriMapper Reconstructs the PSR-7 URI from $_SERVER (defaults to a fresh instance).
     * @param string|null $uploadBaseDir SEC-03: an upload root every `$_FILES`-derived {@see UploadedFile} should
     *        enforce containment against (see {@see UploadedFilesNormalizer}). Only takes effect when
     *        $uploadedFilesNormalizer is left null (the default normalizer is built with it). Created on disk
     *        if it does not already exist — {@see \Waffle\Commons\Utils\Assert::within()} requires the base to
     *        be resolvable.
     */
    public function __construct(
        ?callable $bodyStreamFactory = null,
        ?UploadedFilesNormalizer $uploadedFilesNormalizer = null,
        ?ServerRequestHeadersMapper $headersMapper = null,
        ?ServerRequestUriMapper $uriMapper = null,
        ?string $uploadBaseDir = null,
    ) {
        // Provides a default factory if none is given
        $this->bodyStreamFactory = $bodyStreamFactory ?? static function (): Stream {
            $resource = fopen('php://input', mode: 'r');
            if (false === $resource) {
                throw new RuntimeException('Failed to open php://input stream.');
            }
            assert(is_resource($resource), description: 'fopen must return a resource after false check.');
            return new Stream($resource);
        };
        $this->uploadedFilesNormalizer =
            $uploadedFilesNormalizer ?? new UploadedFilesNormalizer($this->ensureUploadBaseDir($uploadBaseDir));
        $this->headersMapper = $headersMapper ?? new ServerRequestHeadersMapper();
        $this->uriMapper = $uriMapper ?? new ServerRequestUriMapper();
    }

    /**
     * Creates $uploadBaseDir on disk when it does not already exist, so a
     * boot-time-configured upload root is always ready for
     * {@see \Waffle\Commons\Utils\Assert::within()} (which requires an
     * existing, `realpath()`-resolvable base) the first time an upload lands
     * — no separate provisioning step for the app to remember.
     */
    private function ensureUploadBaseDir(?string $uploadBaseDir): ?string
    {
        if ($uploadBaseDir === null) {
            return null;
        }

        if (!is_dir($uploadBaseDir)) {
            mkdir($uploadBaseDir, 0o775, true);
        }

        return $uploadBaseDir;
    }

    /**
     * Creates a ServerRequest from PHP superglobals.
     *
     * @return ServerRequestInterface
     */
    #[\Override]
    public function createFromGlobals(): ServerRequestInterface
    {
        /** @var array<string, string> $server The CGI/SAPI server parameters; values are strings. */
        $server = $_SERVER;

        // Method, URI, Headers, Body, Version
        $method = $server['REQUEST_METHOD'] ?? 'GET';
        $uri = $this->uriMapper->map($server);
        $headers = $this->headersMapper->map($server);
        $body = ($this->bodyStreamFactory)(); // Creates the body stream
        $protocol = str_replace(search: 'HTTP/', replace: '', subject: $server['SERVER_PROTOCOL'] ?? '1.1');

        // ServerRequest-specific parameters
        $cookies = $_COOKIE;
        $queryParams = $_GET;
        $uploadedFiles = $this->createUploadedFilesFromGlobals();
        // Parsed body (depends on method and Content-Type)
        $parsedBody = $this->getParsedBody($method, $headers, $body);

        return new ServerRequest(
            $method,
            $uri,
            $headers,
            $body,
            $protocol,
            $server,
            $cookies,
            $queryParams,
            $parsedBody,
            $uploadedFiles,
        ); // serverParams
    }

    /**
     * Retrieves the parsed body (e.g., $_POST or decoded JSON).
     *
     * Dispatches to a per-content-type helper so the top-level method stays a
     * flat decision table (Beta-1 hardening: reduce cyclomatic complexity per
     * Roadmap §1.5).
     *
     * @param array<string, string> $headers
     * @return array|object|null
     */
    private function getParsedBody(string $method, array $headers, StreamInterface $body): array|object|null
    {
        if ('POST' !== $method) {
            return null;
        }

        $mime = $this->extractMimeType($headers);

        if ($this->isFormPostMime($mime)) {
            return $_POST;
        }

        if ('application/json' === $mime) {
            return $this->parseJsonBody($body);
        }

        return null;
    }

    /**
     * Extracts the bare MIME type from a Content-Type header value
     * (strips charset, boundary, and any other parameters).
     *
     * @param array<string, string> $headers
     */
    private function extractMimeType(array $headers): string
    {
        $contentType = $headers['content-type'] ?? '';
        $parts = explode(separator: ';', string: $contentType);
        return mb_trim(strtolower($parts[0]));
    }

    /**
     * @return bool Whether the MIME type belongs to the form-POST family
     *              (urlencoded or multipart) — both bodies are parsed into $_POST.
     */
    private function isFormPostMime(string $mime): bool
    {
        return $mime === 'application/x-www-form-urlencoded' || $mime === 'multipart/form-data';
    }

    /**
     * Decodes a JSON request body into an array/object. Returns `null` on empty
     * body, malformed JSON, or scalar/null payloads.
     */
    private function parseJsonBody(StreamInterface $body): array|object|null
    {
        try {
            $bodyContents = $body->getContents();
            if ($bodyContents === '') {
                return null;
            }
            $decoded = json_decode(json: $bodyContents, associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);
            return is_array($decoded) || is_object($decoded) ? $decoded : null;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Creates and normalizes the uploaded files structure from $_FILES.
     *
     * @return array<string, UploadedFileInterface>
     */
    private function createUploadedFilesFromGlobals(): array
    {
        return $this->uploadedFilesNormalizer->normalize($_FILES);
    }
}
