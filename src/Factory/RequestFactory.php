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
 */
class RequestFactory
{
    /**
     * @var callable Factory to create a Stream for php://input.
     */
    private $bodyStreamFactory;

    /**
     * @param callable|null $bodyStreamFactory
     */
    public function __construct(null|callable $bodyStreamFactory = null)
    {
        // Provides a default factory if none is given
        $this->bodyStreamFactory = $bodyStreamFactory ?? function (): Stream {
            $resource = fopen('php://input', 'r');
            if (false === $resource) {
                throw new RuntimeException('Failed to open php://input stream.');
            }
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
        $protocol = str_replace('HTTP/', '', $_SERVER['SERVER_PROTOCOL'] ?? '1.1');

        // ServerRequest-specific parameters
        $cookies = $_COOKIE ?? [];
        $queryParams = $_GET ?? [];
        $uploadedFiles = $this->createUploadedFilesFromGlobals();
        // Parsed body (depends on method and Content-Type)
        $parsedBody = $this->getParsedBody($method, $headers, $body);

        return new ServerRequest(
            $method,
            $uri,
            $headers,
            $body,
            $protocol,
            $_SERVER, // serverParams
            $cookies,
            $queryParams,
            $parsedBody,
            $uploadedFiles,
        );
    }

    /**
     * Creates a Uri object from globals.
     *
     * @return UriInterface
     */
    private function createUriFromGlobals(): UriInterface
    {
        $scheme = 'http';
        if (isset($_SERVER['HTTPS']) && ('on' === $_SERVER['HTTPS'] || 1 === (int) $_SERVER['HTTPS'])) {
            $scheme = 'https';
        }

        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

        // Separates host and port if HTTP_HOST contains both
        if (1 === preg_match('/^(.+):(\d+)$/', $host, $matches)) {
            $host = $matches[1];
            $port = (int) $matches[2];
        } else {
            // Otherwise, use SERVER_PORT or standard port
            $port = (int) ($_SERVER['SERVER_PORT'] ?? ('http' === $scheme ? 80 : 443));
        }

        $path = $_SERVER['REQUEST_URI'] ?? '/';
        // Removes query string from path
        $path = explode('?', $path, 2)[0];

        $query = $_SERVER['QUERY_STRING'] ?? '';
        $fragment = ''; // Fragment is never sent to server

        // Handles Basic/Digest authentication
        $user = $_SERVER['PHP_AUTH_USER'] ?? null;
        $pass = $_SERVER['PHP_AUTH_PW'] ?? null;
        $userInfo = '';
        if (null !== $user) {
            $userInfo = $user . (null !== $pass ? ':' . $pass : '');
        }

        // Reconstructs a full URI string to pass to the Uri constructor,
        // which will handle final normalization (e.g., standard port).
        $uriString = $scheme . '://';
        if ('' !== $userInfo) {
            $uriString .= $userInfo . '@';
        }
        $uriString .= $host;
        // Only adds port if it's non-standard
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
     * Retrieves HTTP headers from $_SERVER.
     *
     * @return array<string, string>
     */
    private function getHeadersFromGlobals(): array
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                // Converts HTTP_CONTENT_TYPE to content-type
                $headerName = str_replace('_', '-', strtolower(substr($name, 5)));
                $headers[$headerName] = $value;
            } elseif (in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                // Handles headers without HTTP_ prefix
                $headerName = str_replace('_', '-', strtolower($name));
                $headers[$headerName] = $value;
            }
        }

        // Handles Authorization header (often handled differently by SAPIs)
        if (!isset($headers['authorization'])) {
            if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $headers['authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (isset($_SERVER['PHP_AUTH_USER'])) {
                $basicAuth = base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . ($_SERVER['PHP_AUTH_PW'] ?? ''));
                $headers['authorization'] = 'Basic ' . $basicAuth;
            } elseif (isset($_SERVER['PHP_AUTH_DIGEST'])) {
                $headers['authorization'] = $_SERVER['PHP_AUTH_DIGEST'];
            }
        }

        return $headers;
    }

    /**
     * Retrieves the parsed body (e.g., $_POST or decoded JSON).
     *
     * @param string $method
     * @param array $headers
     * @param StreamInterface $body
     * @return array|object|null
     */
    private function getParsedBody(string $method, array $headers, StreamInterface $body)
    {
        // No parsed body for GET/HEAD/etc. requests
        if ('POST' !== $method) {
            return null;
        }

        $contentType = $headers['content-type'] ?? '';
        $parts = explode(';', $contentType);
        $mime = trim(strtolower($parts[0]));

        if ('application/x-www-form-urlencoded' === $mime) {
            return $_POST ?? null;
        }

        if ('multipart/form-data' === $mime) {
            // Per PSR-7, $_POST data is returned here.
            // $_FILES is handled separately by getUploadedFiles().
            return $_POST ?? null;
        }

        if ('application/json' === $mime) {
            try {
                $bodyContents = $body->getContents();
                if ($bodyContents === '') {
                    return null; // Empty JSON body
                }
                // Attempts to decode JSON
                return json_decode($bodyContents, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Exception $e) {
                return null; // JSON parsing failed
            }
        }

        // Unknown or unparsable content type
        return null;
    }

    /**
     * Creates and normalizes the uploaded files structure from $_FILES.
     *
     * @return array<string, UploadedFileInterface>
     * @throws InvalidArgumentException
     */
    private function createUploadedFilesFromGlobals(): array
    {
        if (empty($_FILES)) {
            return [];
        }
        return $this->normalizeFiles($_FILES);
    }

    /**
     * Normalizes the $_FILES structure (which can be complex).
     *
     * @param array $files The $_FILES array.
     * @return array<string, UploadedFileInterface|array>
     * @throws InvalidArgumentException
     */
    private function normalizeFiles(array $files): array
    {
        $normalized = [];
        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFileInterface) {
                $normalized[$key] = $value;
            } elseif (is_array($value) && isset($value['tmp_name'])) {
                // This is a "leaf" (actual file) or an array of "leafs"
                $normalized[$key] = $this->createUploadedFileFromSpec($value);
            } elseif (is_array($value)) {
                // This is a nested sub-array (e.g., <input name="details[avatar]">)
                $normalized[$key] = $this->normalizeFiles($value);
            } else {
                throw new InvalidArgumentException('Invalid value in $_FILES array.');
            }
        }
        return $normalized;
    }

    /**
     * Creates UploadedFile instances from a normalized $_FILES spec.
     *
     * @param array $spec
     * @return UploadedFileInterface|UploadedFileInterface[]
     */
    private function createUploadedFileFromSpec(array $spec): UploadedFileInterface|array
    {
        if (is_array($spec['tmp_name'])) {
            // Handles the <input name="files[]"> case
            return $this->normalizeNestedFileSpec($spec);
        }

        // Single file case
        return new UploadedFile(
            $spec['tmp_name'],
            (int) ($spec['size'] ?? 0),
            (int) ($spec['error'] ?? UPLOAD_ERR_OK),
            $spec['name'] ?? null,
            $spec['type'] ?? null,
        );
    }

    /**
     * Handles the nested structure of <input name="files[]">.
     *
     * @param array $files
     * @return array<int, UploadedFileInterface>
     */
    private function normalizeNestedFileSpec(array $files): array
    {
        $normalized = [];
        // Iterates over 'tmp_name' keys (0, 1, 2...)
        foreach (array_keys($files['tmp_name']) as $key) {
            $spec = [
                'tmp_name' => $files['tmp_name'][$key],
                'size' => $files['size'][$key] ?? 0,
                'error' => $files['error'][$key] ?? UPLOAD_ERR_OK,
                'name' => $files['name'][$key] ?? null,
                'type' => $files['type'][$key] ?? null,
            ];
            // Creates the UploadedFile instance for this index
            $normalized[$key] = $this->createUploadedFileFromSpec($spec);
        }
        return $normalized;
    }
}
