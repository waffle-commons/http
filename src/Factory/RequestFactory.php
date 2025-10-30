<?php

declare(strict_types=1);

namespace Waffle\Commons\Http\Factory;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Waffle\Commons\Http\ServerRequest;
use Waffle\Commons\Http\Stream;
use Waffle\Commons\Http\UploadedFile;
use Waffle\Commons\Http\Uri;

class RequestFactory
{
    /**
     * @var callable
     */
    private $bodyStreamFactory;

    /**
     * @param callable|null $bodyStreamFactory Factory to create a Stream for php://input.
     */
    public function __construct(null|callable $bodyStreamFactory = null)
    {
        $this->bodyStreamFactory = $bodyStreamFactory ?? function (): Stream {
            $resource = fopen('php://input', 'r');
            if (false === $resource) {
                throw new RuntimeException('Failed to open php://input stream.');
            }
            return new Stream($resource);
        };
    }

    public function createFromGlobals(): ServerRequestInterface
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $this->createUriFromGlobals();
        $headers = $this->getHeadersFromGlobals();
        $body = ($this->bodyStreamFactory)();
        $protocol = str_replace('HTTP/', '', $_SERVER['SERVER_PROTOCOL'] ?? '1.1');
        $cookies = $_COOKIE ?? [];
        $queryParams = $_GET ?? [];
        $uploadedFiles = $this->createUploadedFilesFromGlobals();
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
        );
    }

    private function createUriFromGlobals(): UriInterface
    {
        $scheme = 'http';
        if (isset($_SERVER['HTTPS']) && ('on' === $_SERVER['HTTPS'] || 1 === (int) $_SERVER['HTTPS'])) {
            $scheme = 'https';
        }

        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

        // Remove port from host if present
        if (1 === preg_match('/^(.+):(\d+)$/', $host, $matches)) {
            $host = $matches[1];
            $port = (int) $matches[2];
        } else {
            $port = (int) ($_SERVER['SERVER_PORT'] ?? ('http' === $scheme ? 80 : 443));
        }

        $path = $_SERVER['REQUEST_URI'] ?? '/';
        // Remove query string from path
        $path = explode('?', $path, 2)[0];

        $query = $_SERVER['QUERY_STRING'] ?? '';
        $fragment = ''; // Cannot be determined from server-side

        $user = $_SERVER['PHP_AUTH_USER'] ?? null;
        $pass = $_SERVER['PHP_AUTH_PW'] ?? null;
        $userInfo = '';
        if (null !== $user) {
            $userInfo = $user . (null !== $pass ? ':' . $pass : '');
        }

        // Reconstruct URI from parts to let the Uri object handle normalization
        $uriString = $scheme . '://';
        if ('' !== $userInfo) {
            $uriString .= $userInfo . '@';
        }
        $uriString .= $host;
        // Only add port if it's non-standard
        if (!('http' === $scheme && 80 === $port || 'https' === $scheme && 443 === $port)) {
            $uriString .= ':' . $port;
        }
        $uriString .= $path;
        if ('' !== $query) {
            $uriString .= '?' . $query;
        }

        return new Uri($uriString);
    }

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

        // Handle Authorization header (often stripped)
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

    private function getParsedBody(string $method, array $headers, Stream $body)
    {
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
            // Per PSR-7, $_POST data is returned here. $_FILES are separate.
            return $_POST ?? null;
        }

        if ('application/json' === $mime) {
            try {
                $bodyContents = $body->getContents();
                if ($bodyContents === '') {
                    return null;
                }
                return json_decode($bodyContents, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Exception $e) {
                return null; // Failed to parse JSON
            }
        }

        return null;
    }

    /**
     * @return array
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
     * @param array $files
     * @return array
     * @throws InvalidArgumentException
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
     * Create UploadedFile instances from a normalized $_FILES spec.
     *
     * @param array $spec
     * @return UploadedFileInterface|UploadedFileInterface[]
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
     * @param array $files
     * @return array
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
