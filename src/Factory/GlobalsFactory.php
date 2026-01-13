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
 */
class GlobalsFactory
{
    /**
     * @var callable Factory to create a Stream for php://input.
     */
    private $bodyStreamFactory;

    /**
     * @param callable|null $bodyStreamFactory Factory to create a Stream for php://input.
     */
    public function __construct(
        ?callable $bodyStreamFactory = null,
        private readonly array $trustedHosts = [],
    ) {
        // Provides a default factory if none is given
        $this->bodyStreamFactory = $bodyStreamFactory ?? static function (): Stream {
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
        $server = $_SERVER;

        // Security Check: Trusted Hosts
        if (!empty($this->trustedHosts)) {
            $host = $server['HTTP_HOST'] ?? null;

            if (!$host) {
                // HTTP 1.0 request without Host header? Reject in modern context.
                throw new InvalidArgumentException('Missing Host header');
            }

            // Remove port if present
            $hostName = preg_replace('/:\d+$/', '', $host);

            if (!in_array($hostName, $this->trustedHosts, true)) {
                // We throw an exception here.
                // The Kernel/Runtime should catch this and return a 400 Bad Request.
                throw new InvalidArgumentException(sprintf(
                    'Untrusted Host "%s". Allowed hosts: %s',
                    $host,
                    implode(', ', $this->trustedHosts),
                ));
            }
        }

        // Method, URI, Headers, Body, Version
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $this->createUriFromGlobals();
        $headers = $this->getHeadersFromGlobals();
        $body = ($this->bodyStreamFactory)(); // Creates the body stream
        $protocol = str_replace('HTTP/', '', $_SERVER['SERVER_PROTOCOL'] ?? '1.1');

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
        $matches = [];

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

        // Basic/Digest authentication handling
        $user = $_SERVER['PHP_AUTH_USER'] ?? null;
        $pass = $_SERVER['PHP_AUTH_PW'] ?? null;
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
     * Retrieves HTTP headers from $_SERVER.
     *
     * @return array<string, string>
     */
    private function getHeadersFromGlobals(): array
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $headerName = str_replace('_', '-', strtolower(substr($name, 5)));
                $headers[$headerName] = $value;
            } elseif (in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $headerName = str_replace('_', '-', strtolower($name));
                $headers[$headerName] = $value;
            }
        }

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
     * @return array|object|null
     */
    private function getParsedBody(string $method, array $headers, StreamInterface $body)
    {
        if ('POST' !== $method) {
            return null;
        }

        $contentType = $headers['content-type'] ?? '';
        $parts = explode(';', $contentType);
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
                return json_decode($bodyContents, true, 512, JSON_THROW_ON_ERROR);
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
        if (empty($_FILES)) {
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
            } elseif (is_array($value) && isset($value['tmp_name'])) {
                $normalized[$key] = $this->createUploadedFileFromSpec($value);
            } elseif (is_array($value)) {
                $normalized[$key] = $this->normalizeFiles($value);
            } else {
                throw new InvalidArgumentException('Invalid value in $_FILES array.');
            }
        }
        return $normalized;
    }

    /**
     * Creates UploadedFile instances from a normalized $_FILES spec.
     */
    private function createUploadedFileFromSpec(array $spec): UploadedFileInterface|array
    {
        if (is_array($spec['tmp_name'])) {
            return $this->normalizeNestedFileSpec($spec);
        }

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
     */
    private function normalizeNestedFileSpec(array $files): array
    {
        $normalized = [];
        foreach (array_keys($files['tmp_name']) as $key) {
            $spec = [
                'tmp_name' => $files['tmp_name'][$key],
                'size' => $files['size'][$key] ?? 0,
                'error' => $files['error'][$key] ?? UPLOAD_ERR_OK,
                'name' => $files['name'][$key] ?? null,
                'type' => $files['type'][$key] ?? null,
            ];
            $normalized[$key] = $this->createUploadedFileFromSpec($spec);
        }
        return $normalized;
    }
}
