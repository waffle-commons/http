<?php

declare(strict_types=1);

namespace Waffle\Commons\Http\Factory;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;
use Waffle\Commons\Http\ServerRequest;
use Waffle\Commons\Http\Stream;
use Waffle\Commons\Http\UploadedFile;
use Waffle\Commons\Http\Uri;

/**
 * Factory to create PSR-7 ServerRequest objects.
 *
 * This class reads PHP superglobals (or provided data) to construct
 * a complete ServerRequestInterface object.
 */
class RequestFactory
{
    /**
     * Creates a new server request from PHP's superglobals.
     */
    public function createFromGlobals(): ServerRequestInterface
    {
        $serverParams = $_SERVER;
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $headers = $this->normalizeHeaders($serverParams);
        $uri = $this->createUriFromGlobals($serverParams);
        $body = new Stream(fopen('php://input', 'r') ?: 'php://temp');
        $protocolVersion = isset($serverParams['SERVER_PROTOCOL'])
            ? str_replace('HTTP/', '', $serverParams['SERVER_PROTOCOL'])
            : '1.1';
        $cookieParams = $_COOKIE;
        $queryParams = $_GET;
        $uploadedFiles = $this->normalizeUploadedFiles($_FILES);
        $parsedBody = $this->parseBody($headers, $body, $_POST);

        return new ServerRequest(
            method: $method,
            uri: $uri,
            headers: $headers,
            body: $body,
            protocolVersion: $protocolVersion,
            serverParams: $serverParams,
            cookieParams: $cookieParams,
            queryParams: $queryParams,
            uploadedFiles: $uploadedFiles,
            parsedBody: $parsedBody,
        );
    }

    /**
     * Parses the body based on Content-Type.
     *
     * @param array<string, string|string[]> $headers
     * @param StreamInterface $body
     * @param array<string, mixed> $postData
     * @return mixed
     */
    private function parseBody(array $headers, StreamInterface $body, array $postData): mixed
    {
        $contentType = $headers['content-type'][0] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $bodyContents = $body->getContents();
            $parsed = json_decode($bodyContents, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $parsed;
            }
            return null; // Invalid JSON
        }

        if (
            str_contains($contentType, 'application/x-www-form-urlencoded')
            || str_contains($contentType, 'multipart/form-data')
        ) {
            return $postData;
        }

        return null;
    }

    /**
     * Normalizes server params into a PSR-7 header array.
     *
     * @param array<string, mixed> $server
     * @return array<string, string[]>
     */
    private function normalizeHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                // 'HTTP_CONTENT_TYPE' -> 'content-type'
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = explode(',', $value);
            } elseif (in_array(strtolower($key), ['content_type', 'content_length', 'content_md5'], true)) {
                // Special cases that don't start with HTTP_
                $name = str_replace('_', '-', strtolower($key));
                $headers[$name] = explode(',', $value);
            }
        }
        return $headers;
    }

    /**
     * Normalizes the chaotic $_FILES array into a PSR-7 UploadedFileInterface tree.
     *
     * @param array<string, mixed> $files
     * @return array<string, UploadedFileInterface|array>
     */
    private function normalizeUploadedFiles(array $files): array
    {
        $normalized = [];
        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFileInterface) {
                $normalized[$key] = $value;
            } elseif (is_array($value) && isset($value['tmp_name'])) {
                // Check if it's a single file or array of files
                if (is_array($value['tmp_name'])) {
                    $normalized[$key] = $this->normalizeNestedFiles($value);
                } else {
                    $normalized[$key] = new UploadedFile(
                        $value['tmp_name'],
                        (int) ($value['size'] ?? 0),
                        (int) ($value['error'] ?? UPLOAD_ERR_OK),
                        $value['name'] ?? null,
                        $value['type'] ?? null,
                    );
                }
            }
        }
        return $normalized;
    }

    /**
     * Helper for normalizeUploadedFiles to handle nested arrays.
     *
     * @param array<string, mixed> $filesData
     * @return array<int|string, UploadedFileInterface>
     */
    private function normalizeNestedFiles(array $filesData): array
    {
        $normalized = [];
        $keys = array_keys($filesData['tmp_name']);

        foreach ($keys as $key) {
            $normalized[$key] = new UploadedFile(
                $filesData['tmp_name'][$key],
                (int) ($filesData['size'][$key] ?? 0),
                (int) ($filesData['error'][$key] ?? UPLOAD_ERR_OK),
                $filesData['name'][$key] ?? null,
                $filesData['type'][$key] ?? null,
            );
        }

        return $normalized;
    }

    /**
     * Creates a UriInterface from server globals.
     *
     * @param array<string, mixed> $server
     */
    private function createUriFromGlobals(array $server): UriInterface
    {
        $scheme = isset($server['HTTPS']) && $server['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? 'localhost';
        $port = isset($server['SERVER_PORT']) ? (int) $server['SERVER_PORT'] : null;
        $path = $server['REQUEST_URI'] ?? '/';
        $query = $server['QUERY_STRING'] ?? '';
        $fragment = ''; // Fragment is not available in server globals

        // Remove query string from path
        if (str_contains($path, '?')) {
            $path = explode('?', $path, 2)[0];
        }

        // Build authority part (host + port)
        if ($port !== null) {
            if ($scheme === 'http' && $port !== 80 || $scheme === 'https' && $port !== 443) {
                $host .= ':' . $port;
            }
        }

        return new Uri($scheme, $host, $port, $path, $query, $fragment);
    }
}
