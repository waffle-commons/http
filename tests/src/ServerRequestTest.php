<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Http;

use InvalidArgumentException;
use Waffle\Commons\Http\ServerRequest;
use Waffle\Commons\Http\Stream;
use Waffle\Commons\Http\Uri;

class ServerRequestTest extends AbstractTestCase
{
    private function createTestRequest(
        string $method = 'GET',
        string $uri = '/',
        array $serverParams = [],
        array $cookieParams = [],
        array $queryParams = [],
        $parsedBody = null,
        array $uploadedFiles = [],
    ): ServerRequest {
        return new ServerRequest(
            $method,
            new Uri($uri),
            [],
            $this->createStream(),
            '1.1',
            $serverParams,
            $cookieParams,
            $queryParams,
            $parsedBody,
            $uploadedFiles,
        ); // Headers // Body
    }

    public function testConstructorAcceptsResourceBody(): void
    {
        $resource = fopen(filename: 'php://memory', mode: 'r+');
        static::assertIsResource($resource);
        fwrite(stream: $resource, data: 'Resource Body');
        fseek(stream: $resource, offset: 0);

        $request = new ServerRequest('POST', new Uri('/'), [], $resource);

        static::assertInstanceOf(Stream::class, $request->getBody());
        static::assertSame('Resource Body', (string) $request->getBody());
    }

    public function testConstructorThrowsExceptionForInvalidBodyType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid body type');

        new ServerRequest('POST', new Uri('/'), [], 12_345); // Int is invalid
    }

    public function testRequestTargetDefaultsToSlash(): void
    {
        $request = $this->createTestRequest('GET', '');
        static::assertSame('/', $request->getRequestTarget());
    }

    public function testRequestTargetIncludesQuery(): void
    {
        $request = new ServerRequest('GET', new Uri('/path?foo=bar'));
        static::assertSame('/path?foo=bar', $request->getRequestTarget());
    }

    public function testWithRequestTarget(): void
    {
        $r1 = $this->createTestRequest();
        $r2 = $r1->withRequestTarget('*');

        static::assertNotSame($r1, $r2);
        static::assertSame('*', $r2->getRequestTarget());
    }

    public function testWithRequestTargetThrowsExceptionForWhitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid request target provided; must not contain whitespace.');

        $this->createTestRequest()->withRequestTarget('/path with spaces');
    }

    public function testWithMethod(): void
    {
        $r1 = $this->createTestRequest('GET');
        $r2 = $r1->withMethod('POST');
        $r3 = $r1->withMethod('GET'); // Same method

        static::assertNotSame($r1, $r2);
        static::assertSame('POST', $r2->getMethod());
        static::assertNotSame($r1, $r3); // Clones even if same
    }

    public function testWithUriPreservesHostHeaderIfPresent(): void
    {
        $uri1 = new Uri('http://example.com');
        $request = new ServerRequest('GET', $uri1, ['Host' => 'example.com']);

        $uri2 = new Uri('http://other.com');
        $newRequest = $request->withUri($uri2, true); // preserveHost = true

        static::assertSame('example.com', $newRequest->getHeaderLine('Host'));
        static::assertSame($uri2, $newRequest->getUri());
    }

    public function testWithUriUpdatesHostHeaderIfNotPreserved(): void
    {
        $request = $this->createTestRequest();
        $uri = new Uri('http://example.com');

        $newRequest = $request->withUri($uri); // preserveHost = false (default)

        static::assertSame('example.com', $newRequest->getHeaderLine('Host'));
    }

    public function testWithUriAddsPortToHostHeader(): void
    {
        $request = $this->createTestRequest();
        $uri = new Uri('http://example.com:8080');

        $newRequest = $request->withUri($uri);

        static::assertSame('example.com:8080', $newRequest->getHeaderLine('Host'));
    }

    public function testGetServerParams(): void
    {
        $params = ['REQUEST_TIME' => 123_456];
        $request = $this->createTestRequest('GET', '/', $params);
        static::assertSame($params, $request->getServerParams());
    }

    public function testGetCookieParams(): void
    {
        $cookies = ['user' => 'waffle'];
        $request = $this->createTestRequest('GET', '/', [], $cookies);
        static::assertSame($cookies, $request->getCookieParams());
    }

    public function testWithCookieParams(): void
    {
        $cookies1 = ['user' => 'waffle'];
        $cookies2 = ['user' => 'framework'];
        $r1 = $this->createTestRequest('GET', '/', [], $cookies1);
        $r2 = $r1->withCookieParams($cookies2);

        static::assertNotSame($r1, $r2);
        static::assertSame($cookies1, $r1->getCookieParams());
        static::assertSame($cookies2, $r2->getCookieParams());
    }

    public function testGetQueryParams(): void
    {
        $query = ['page' => '1'];
        $request = $this->createTestRequest('GET', '/', [], [], $query);
        static::assertSame($query, $request->getQueryParams());
    }

    public function testWithQueryParams(): void
    {
        $query1 = ['page' => '1'];
        $query2 = ['page' => '2', 'sort' => 'asc'];
        $r1 = $this->createTestRequest('GET', '/', [], [], $query1);
        $r2 = $r1->withQueryParams($query2);

        static::assertNotSame($r1, $r2);
        static::assertSame($query1, $r1->getQueryParams());
        static::assertSame($query2, $r2->getQueryParams());
    }

    public function testGetUploadedFiles(): void
    {
        $files = ['avatar' => new \stdClass()]; // Simulates UploadedFileInterface
        $request = $this->createTestRequest('POST', '/', [], [], [], null, $files);
        static::assertSame($files, $request->getUploadedFiles());
    }

    public function testWithUploadedFiles(): void
    {
        $files1 = ['avatar' => new \stdClass()]; // Simulate
        $files2 = ['doc' => new \stdClass()]; // Simulate
        $r1 = $this->createTestRequest('POST', '/', [], [], [], null, $files1);
        $r2 = $r1->withUploadedFiles($files2);

        static::assertNotSame($r1, $r2);
        static::assertSame($files1, $r1->getUploadedFiles());
        static::assertSame($files2, $r2->getUploadedFiles());
    }

    public function testGetParsedBody(): void
    {
        $body = ['username' => 'waffle'];
        $request = $this->createTestRequest('POST', '/', [], [], [], $body);
        static::assertSame($body, $request->getParsedBody());
    }

    public function testWithParsedBody(): void
    {
        $body1 = ['username' => 'waffle'];
        $body2 = ['username' => 'framework'];
        $r1 = $this->createTestRequest('POST', '/', [], [], [], $body1);
        $r2 = $r1->withParsedBody($body2);

        static::assertNotSame($r1, $r2);
        static::assertSame($body1, $r1->getParsedBody());
        static::assertSame($body2, $r2->getParsedBody());
    }

    public function testWithParsedBodyThrowsExceptionForInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->createTestRequest()->withParsedBody(123); // Int not allowed
    }

    public function testGetAttributes(): void
    {
        $r1 = $this->createTestRequest();
        $r2 = $r1->withAttribute('user', 123);
        $r3 = $r2->withAttribute('role', 'admin');

        static::assertSame([], $r1->getAttributes());
        static::assertSame(['user' => 123], $r2->getAttributes());
        static::assertSame(['user' => 123, 'role' => 'admin'], $r3->getAttributes());
    }

    public function testGetAttribute(): void
    {
        $r1 = $this->createTestRequest()->withAttribute('user', 123);

        static::assertSame(123, $r1->getAttribute('user'));
        static::assertNull($r1->getAttribute('non_existent'));
        static::assertSame('default', $r1->getAttribute('non_existent', 'default'));
    }

    public function testWithAttribute(): void
    {
        $r1 = $this->createTestRequest();
        $r2 = $r1->withAttribute('user', 123);

        static::assertNotSame($r1, $r2);
        static::assertNull($r1->getAttribute('user'));
        static::assertSame(123, $r2->getAttribute('user'));
    }

    public function testWithoutAttribute(): void
    {
        $r1 = $this->createTestRequest()->withAttribute('user', 123)->withAttribute('role', 'admin');

        $r2 = $r1->withoutAttribute('user');

        static::assertNotSame($r1, $r2);
        static::assertSame(['user' => 123, 'role' => 'admin'], $r1->getAttributes());
        static::assertSame(['role' => 'admin'], $r2->getAttributes());
        static::assertNull($r2->getAttribute('user'));
    }

    public function testWithoutAttributeReturnsCloneWhenAttributeAbsent(): void
    {
        $r1 = $this->createTestRequest();

        $r2 = $r1->withoutAttribute('never-set');

        // PSR-7 compliance: still returns a new instance even when no-op
        static::assertNotSame($r1, $r2);
        static::assertSame([], $r2->getAttributes());
    }

    public function testWithUriReturnsCloneWithoutHostUpdateWhenNoHost(): void
    {
        $r1 = $this->createTestRequest();
        $noHostUri = new \Waffle\Commons\Http\Uri('/relative/only');

        $r2 = $r1->withUri($noHostUri, false);

        static::assertNotSame($r1, $r2);
        static::assertSame($noHostUri, $r2->getUri());
    }

    public function testWithUriPreservingHost(): void
    {
        $r1 = $this->createTestRequest();
        $r1 = $r1->withHeader('Host', 'preserved.example');
        $newUri = new \Waffle\Commons\Http\Uri('http://other.example.org/api');

        $r2 = $r1->withUriPreservingHost($newUri);

        static::assertNotSame($r1, $r2);
        static::assertSame($newUri, $r2->getUri());
        // Host header is preserved
        static::assertSame('preserved.example', $r2->getHeaderLine('Host'));
    }
}
