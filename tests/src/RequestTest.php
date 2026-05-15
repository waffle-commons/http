<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Http;

use InvalidArgumentException;
use Waffle\Commons\Http\Request;
use Waffle\Commons\Http\Uri;

class RequestTest extends AbstractTestCase
{
    public function testConstructor(): void
    {
        $uri = new Uri('https://example.com/');
        $request = new Request('GET', $uri);

        static::assertSame('GET', $request->getMethod());
        static::assertSame($uri, $request->getUri());
        static::assertSame('/', $request->getRequestTarget());
        // Host header should be set automatically from URI
        static::assertSame('example.com', $request->getHeaderLine('Host'));
    }

    public function testConstructorWithStringUri(): void
    {
        // The constructor expects a UriInterface.
        $uri = new Uri('https://example.com/');
        $request = new Request('GET', $uri);

        static::assertSame($uri, $request->getUri());
    }

    public function testGetRequestTarget(): void
    {
        $request = new Request('GET', new Uri('/'));
        static::assertSame('/', $request->getRequestTarget());

        // Test with query string
        $request = new Request('GET', new Uri('/path?query=1'));
        static::assertSame('/path?query=1', $request->getRequestTarget());
    }

    public function testGetRequestTargetReturnsStoredTarget(): void
    {
        $request = new Request('GET', new Uri('/'));
        $newRequest = $request->withRequestTarget('*');

        static::assertSame('*', $newRequest->getRequestTarget());
        // Original should stay same
        static::assertSame('/', $request->getRequestTarget());
    }

    public function testWithRequestTarget(): void
    {
        $request = new Request('GET', new Uri('/'));
        $newRequest = $request->withRequestTarget('/new-target');

        static::assertNotSame($request, $newRequest);
        static::assertSame('/new-target', $newRequest->getRequestTarget());
    }

    public function testWithRequestTargetThrowsExceptionForWhitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid request target provided; must not contain whitespace.');

        $request = new Request('GET', new Uri('/'));
        $request->withRequestTarget('/path with spaces');
    }

    public function testWithMethod(): void
    {
        $request = new Request('GET', new Uri('/'));
        $newRequest = $request->withMethod('POST');

        static::assertNotSame($request, $newRequest);
        static::assertSame('GET', $request->getMethod());
        static::assertSame('POST', $newRequest->getMethod());
    }

    public function testWithUri(): void
    {
        $uri1 = new Uri('http://example.com/1');
        $uri2 = new Uri('http://example.org/2');

        $request = new Request('GET', $uri1);
        $newRequest = $request->withUri($uri2);

        static::assertNotSame($request, $newRequest);
        static::assertSame($uri2, $newRequest->getUri());
        static::assertSame('example.org', $newRequest->getHeaderLine('Host'));
    }

    public function testWithUriPreservesHost(): void
    {
        $uri1 = new Uri('http://example.com');
        $uri2 = new Uri('http://example.org');

        $request = new Request('GET', $uri1);
        // We manually set a Host header
        $request = $request->withHeader('Host', 'custom.com');

        // We update URI but ask to preserve host
        $newRequest = $request->withUri($uri2, true);

        static::assertSame('custom.com', $newRequest->getHeaderLine('Host'));
        static::assertSame($uri2, $newRequest->getUri());
    }

    public function testWithUriUpdatesHostIfPreserveHostIsFalse(): void
    {
        $uri1 = new Uri('http://example.com');
        $uri2 = new Uri('http://example.org');

        $request = new Request('GET', $uri1);
        $request = $request->withHeader('Host', 'old.com');

        $newRequest = $request->withUri($uri2, false); // Default behavior

        static::assertSame('example.org', $newRequest->getHeaderLine('Host'));
    }

    public function testWithUriDoesNotUpdateHostIfNoHostInUri(): void
    {
        $request = new Request('GET', new Uri('http://example.com'));
        $noHostUri = new Uri('/path/only'); // No host in this URI

        $newRequest = $request->withUri($noHostUri);

        // Host header should remain from the original request (example.com)
        static::assertSame('example.com', $newRequest->getHeaderLine('Host'));
    }

    public function testGetRequestTargetReturnsSlashWhenPathIsEmpty(): void
    {
        // Uri with no path component → getPath() returns ''
        $request = new Request('GET', new Uri('http://example.com'));

        static::assertSame('/', $request->getRequestTarget());
    }

    public function testConstructorAppendsPortToHostHeader(): void
    {
        // Uri with explicit non-standard port should produce 'host:port' Host header
        $request = new Request('GET', new Uri('http://example.com:8080/api'));

        static::assertSame('example.com:8080', $request->getHeaderLine('Host'));
    }

    public function testWithUriPreservingHost(): void
    {
        $uri1 = new Uri('http://example.com');
        $uri2 = new Uri('http://other.example.org');

        $request = new Request('GET', $uri1);
        $request = $request->withHeader('Host', 'custom.com');

        $new = $request->withUriPreservingHost($uri2);

        static::assertNotSame($request, $new);
        static::assertSame($uri2, $new->getUri());
        // Host preserved from original
        static::assertSame('custom.com', $new->getHeaderLine('Host'));
    }
}
