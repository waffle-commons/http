<?php

declare(strict_types=1);

namespace Waffle\Commons\Http\Factory;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Waffle\Commons\Http\ServerRequest;
use Waffle\Commons\Http\Stream;
use Waffle\Commons\Http\UploadedFile;
use Waffle\Commons\Http\Uri;

/**
 * Creates a ServerRequestInterface (PSR-7) instance from PHP superglobals.
 *
 * This factory is specific to the Waffle framework's bootstrap process.
 *
 * Trusted-host enforcement is NOT performed here. Per RFC-003 §3.2 (Alpha 6 P0),
 * Host Header Injection is rejected by `Waffle\Commons\Pipeline\Middleware\TrustedHostMiddleware`
 * which sits between ErrorHandlerMiddleware and CoreRoutingMiddleware in the PSR-15 stack.
 */
class GlobalsFactory
{
    /**
     * @var callable(): StreamInterface
     */
    private $bodyStreamFactory;

    /**
     * @param (callable(): StreamInterface)|null $bodyStreamFactory Factory to create a Stream for php://input.
     */
    public function __construct(?callable $bodyStreamFactory = null)
    {
        // Provides a default factory if none is given
        $this->bodyStreamFactory = $bodyStreamFactory ?? static function (): Stream {
            $resource = fopen('php://input', mode: 'r');
            if (false === $resource) {
                throw new RuntimeException('Failed to open php://input stream.');
            }
            assert(is_resource($resource), description: 'fopen must return a resource after false check.');
            return new Stream($resource);
        };
    }

    /**
     * Creates a ServerRequest from PHP superglobals.
     *
     * @return ServerRequestInterface
     */
    public function createFromGlobals(): ServerRequestInterface
    {
        // Method, URI, Headers, Body, Version
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $this->createUriFromGlobals();
        $headers = $this->getHeadersFromGlobals();
        $body = ($this->bodyStreamFactory)(); // Creates the body stream
        $protocol = str_replace(search: 'HTTP/', replace: '', subject: $_SERVER['SERVER_PROTOCOL'] ?? '1.1');

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
            $_SERVER,
            $cookies,
            $queryParams,
            $parsedBody,
            $uploadedFiles,
        ); // serverParams
    }

    /**
     * Creates a Uri object from globals.
     *
     * @return UriInterface
     */
    private function createUriFromGlobals(): UriInterface
    {
        $scheme = $this->detectScheme();
        [$host, $port] = $this->extractHostAndPort($scheme);

        $path = $_SERVER['REQUEST_URI'] ?? '/';
        // Removes query string from path
        $path = explode(separator: '?', string: $path, limit: 2)[0];

        $query = $_SERVER['QUERY_STRING'] ?? '';

        // Basic/Digest authentication handling
        $user = array_key_exists('PHP_AUTH_USER', $_SERVER) ? $_SERVER['PHP_AUTH_USER'] : null;
        $pass = array_key_exists('PHP_AUTH_PW', $_SERVER) ? $_SERVER['PHP_AUTH_PW'] : null;
        $userInfo = '';
        if (null !== $user) {
            $userInfo = $user . (null !== $pass ? ':' . $pass : '');
        }

        // Reconstructs a full URI string
        $uriString = $scheme . '://';
        if ('' !== $userInfo) {
            $uriString .= $userInfo . '@';
        }
        $uriString .= $host;
        if (!('http' === $scheme && 80 === $port || 'https' === $scheme && 443 === $port)) {
            $uriString .= ':' . $port;
        }
        $uriString .= $path;
        if ('' !== $query) {
            $uriString .= '?' . $query;
        }

        return new Uri($uriString);
    }

    /**
     * Detects the request scheme (http or https) from $_SERVER.
     */
    private function detectScheme(): string
    {
        if (array_key_exists('HTTPS', $_SERVER) && ('on' === $_SERVER['HTTPS'] || 1 === (int) $_SERVER['HTTPS'])) {
            return 'https';
        }
        return 'http';
    }

    /**
     * Extracts host and port from $_SERVER globals.
     *
     * @return array{0: string, 1: int}
     */
    private function extractHostAndPort(string $scheme): array
    {
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $matches = [];

        // Separates host and port if HTTP_HOST contains both
        if (1 === preg_match('/^(.+):(\d+)$/', $host, $matches)) {
            return [$matches[1] ?? $host, (int) ($matches[2] ?? 80)];
        }

        // Otherwise, use SERVER_PORT or standard port
        $port = (int) ($_SERVER['SERVER_PORT'] ?? ('http' === $scheme ? 80 : 443));
        return [$host, $port];
    }

    /**
     * Retrieves HTTP headers from $_SERVER.
     *
     * @return array<string, string>
     */
    private function getHeadersFromGlobals(): array
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $headerName = str_replace(
                    search: '_',
                    replace: '-',
                    subject: strtolower(substr(string: $name, offset: 5)),
                );
                $headers[$headerName] = (string) $value;
                continue;
            }
            if (in_array(needle: $name, haystack: ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], strict: true)) {
                $headerName = str_replace(search: '_', replace: '-', subject: strtolower($name));
                $headers[$headerName] = (string) $value;
            }
        }

        $this->resolveAuthorizationHeader($headers);

        return $headers;
    }

    /**
     * Resolves the authorization header from various $_SERVER sources.
     *
     * @param array<string, string> $headers
     */
    private function resolveAuthorizationHeader(array &$headers): void
    {
        if (array_key_exists('authorization', $headers)) {
            return;
        }
        if (array_key_exists('REDIRECT_HTTP_AUTHORIZATION', $_SERVER)) {
            $headers['authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            return;
        }
        if (array_key_exists('PHP_AUTH_USER', $_SERVER)) {
            $basicAuth = base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . ($_SERVER['PHP_AUTH_PW'] ?? ''));
            $headers['authorization'] = 'Basic ' . $basicAuth;
            return;
        }
        if (array_key_exists('PHP_AUTH_DIGEST', $_SERVER)) {
            $headers['authorization'] = $_SERVER['PHP_AUTH_DIGEST'];
        }
    }

    /**
     * Retrieves the parsed body (e.g., $_POST or decoded JSON).
     *
     * @return array|object|null
     */
    private function getParsedBody(string $method, array $headers, StreamInterface $body): array|object|null
    {
        if ('POST' !== $method) {
            return null;
        }

        $contentType = (string) ($headers['content-type'] ?? '');
        $parts = explode(separator: ';', string: $contentType);
        $mime = trim(strtolower($parts[0]));

        if ('application/x-www-form-urlencoded' === $mime) {
            return $_POST;
        }

        if ('multipart/form-data' === $mime) {
            return $_POST;
        }

        if ('application/json' === $mime) {
            try {
                $bodyContents = $body->getContents();
                if ($bodyContents === '') {
                    return null;
                }
                $decoded = json_decode(json: $bodyContents, associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);
                return is_array($decoded) || is_object($decoded) ? $decoded : null;
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Creates and normalizes the uploaded files structure from $_FILES.
     *
     * @return array<string, UploadedFileInterface>
     */
    private function createUploadedFilesFromGlobals(): array
    {
        if ($_FILES === []) {
            return [];
        }
        return $this->normalizeFiles($_FILES);
    }

    /**
     * Normalizes the $_FILES structure.
     */
    private function normalizeFiles(array $files): array
    {
        $normalized = [];
        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFileInterface) {
                $normalized[$key] = $value;
                continue;
            }
            if (is_array($value) && array_key_exists('tmp_name', $value)) {
                $normalized[$key] = $this->createUploadedFileFromSpec($value);
                continue;
            }
            if (is_array($value)) {
                $normalized[$key] = $this->normalizeFiles($value);
                continue;
            }
            throw new InvalidArgumentException('Invalid value in $_FILES array.');
        }
        return $normalized;
    }

    /**
     * Creates UploadedFile instances from a normalized $_FILES spec.
     */
    private function createUploadedFileFromSpec(array $spec): UploadedFileInterface|array
    {
        $tmpName = (string) ($spec['tmp_name'] ?? '');
        if (is_array($spec['tmp_name'] ?? null)) {
            return $this->normalizeNestedFileSpec($spec);
        }

        return new UploadedFile(
            $tmpName,
            (int) ($spec['size'] ?? 0),
            (int) ($spec['error'] ?? UPLOAD_ERR_OK),
            array_key_exists('name', $spec) ? (string) $spec['name'] : null,
            array_key_exists('type', $spec) ? (string) $spec['type'] : null,
        );
    }

    /**
     * Handles the nested structure of <input name="files[]">.
     */
    private function normalizeNestedFileSpec(array $files): array
    {
        $normalized = [];
        $tmpNames = (array) ($files['tmp_name'] ?? []);
        foreach (array_keys($tmpNames) as $key) {
            $spec = [
                'tmp_name' => $tmpNames[$key],
                'size' => array_key_exists('size', $files) && is_array($files['size']) ? $files['size'][$key] ?? 0 : 0,
                'error' => array_key_exists('error', $files) && is_array($files['error'])
                    ? $files['error'][$key] ?? UPLOAD_ERR_OK
                    : UPLOAD_ERR_OK,
                'name' => array_key_exists('name', $files) && is_array($files['name'])
                    ? $files['name'][$key] ?? null
                    : null,
                'type' => array_key_exists('type', $files) && is_array($files['type'])
                    ? $files['type'][$key] ?? null
                    : null,
            ];
            $normalized[$key] = $this->createUploadedFileFromSpec($spec);
        }
        return $normalized;
    }
}
