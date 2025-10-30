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
            [], // Headers
            $this->createStream(), // Body
            '1.1',
            $serverParams,
            $cookieParams,
            $queryParams,
            $parsedBody,
            $uploadedFiles,
        );
    }

    public function testConstructorAcceptsResourceBody(): void
    {
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, 'Resource Body');
        fseek($resource, 0);

        $request = new ServerRequest('POST', new Uri('/'), [], $resource);

        $this->assertInstanceOf(Stream::class, $request->getBody());
        $this->assertSame('Resource Body', (string) $request->getBody());
    }

    public function testConstructorThrowsExceptionForInvalidBodyType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid body type');

        new ServerRequest('POST', new Uri('/'), [], 12345); // Int is invalid
    }

    public function testRequestTargetDefaultsToSlash(): void
    {
        $request = $this->createTestRequest('GET', '');
        $this->assertSame('/', $request->getRequestTarget());
    }

    public function testRequestTargetIncludesQuery(): void
    {
        $request = new ServerRequest('GET', new Uri('/path?foo=bar'));
        $this->assertSame('/path?foo=bar', $request->getRequestTarget());
    }

    public function testWithRequestTarget(): void
    {
        $r1 = $this->createTestRequest();
        $r2 = $r1->withRequestTarget('*');

        $this->assertNotSame($r1, $r2);
        $this->assertSame('*', $r2->getRequestTarget());
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

        $this->assertNotSame($r1, $r2);
        $this->assertSame('POST', $r2->getMethod());
        $this->assertNotSame($r1, $r3); // Clones even if same
    }

    public function testWithUriPreservesHostHeaderIfPresent(): void
    {
        $uri1 = new Uri('http://example.com');
        $request = new ServerRequest('GET', $uri1, ['Host' => 'example.com']);

        $uri2 = new Uri('http://other.com');
        $newRequest = $request->withUri($uri2, true); // preserveHost = true

        $this->assertSame('example.com', $newRequest->getHeaderLine('Host'));
        $this->assertSame($uri2, $newRequest->getUri());
    }

    public function testWithUriUpdatesHostHeaderIfNotPreserved(): void
    {
        $request = $this->createTestRequest();
        $uri = new Uri('http://example.com');

        $newRequest = $request->withUri($uri); // preserveHost = false (default)

        $this->assertSame('example.com', $newRequest->getHeaderLine('Host'));
    }

    public function testWithUriAddsPortToHostHeader(): void
    {
        $request = $this->createTestRequest();
        $uri = new Uri('http://example.com:8080');

        $newRequest = $request->withUri($uri);

        $this->assertSame('example.com:8080', $newRequest->getHeaderLine('Host'));
    }

    public function testGetServerParams(): void
    {
        $params = ['REQUEST_TIME' => 123456];
        $request = $this->createTestRequest('GET', '/', $params);
        $this->assertSame($params, $request->getServerParams());
    }

    public function testGetCookieParams(): void
    {
        $cookies = ['user' => 'waffle'];
        $request = $this->createTestRequest('GET', '/', [], $cookies);
        $this->assertSame($cookies, $request->getCookieParams());
    }

    public function testWithCookieParams(): void
    {
        $cookies1 = ['user' => 'waffle'];
        $cookies2 = ['user' => 'framework'];
        $r1 = $this->createTestRequest('GET', '/', [], $cookies1);
        $r2 = $r1->withCookieParams($cookies2);

        $this->assertNotSame($r1, $r2);
        $this->assertSame($cookies1, $r1->getCookieParams());
        $this->assertSame($cookies2, $r2->getCookieParams());
    }

    public function testGetQueryParams(): void
    {
        $query = ['page' => '1'];
        $request = $this->createTestRequest('GET', '/', [], [], $query);
        $this->assertSame($query, $request->getQueryParams());
    }

    public function testWithQueryParams(): void
    {
        $query1 = ['page' => '1'];
        $query2 = ['page' => '2', 'sort' => 'asc'];
        $r1 = $this->createTestRequest('GET', '/', [], [], $query1);
        $r2 = $r1->withQueryParams($query2);

        $this->assertNotSame($r1, $r2);
        $this->assertSame($query1, $r1->getQueryParams());
        $this->assertSame($query2, $r2->getQueryParams());
    }

    public function testGetUploadedFiles(): void
    {
        $files = ['avatar' => new \stdClass()]; // Simulates UploadedFileInterface
        $request = $this->createTestRequest('POST', '/', [], [], [], null, $files);
        $this->assertSame($files, $request->getUploadedFiles());
    }

    public function testWithUploadedFiles(): void
    {
        $files1 = ['avatar' => new \stdClass()]; // Simulate
        $files2 = ['doc' => new \stdClass()]; // Simulate
        $r1 = $this->createTestRequest('POST', '/', [], [], [], null, $files1);
        $r2 = $r1->withUploadedFiles($files2);

        $this->assertNotSame($r1, $r2);
        $this->assertSame($files1, $r1->getUploadedFiles());
        $this->assertSame($files2, $r2->getUploadedFiles());
    }

    public function testGetParsedBody(): void
    {
        $body = ['username' => 'waffle'];
        $request = $this->createTestRequest('POST', '/', [], [], [], $body);
        $this->assertSame($body, $request->getParsedBody());
    }

    public function testWithParsedBody(): void
    {
        $body1 = ['username' => 'waffle'];
        $body2 = ['username' => 'framework'];
        $r1 = $this->createTestRequest('POST', '/', [], [], [], $body1);
        $r2 = $r1->withParsedBody($body2);

        $this->assertNotSame($r1, $r2);
        $this->assertSame($body1, $r1->getParsedBody());
        $this->assertSame($body2, $r2->getParsedBody());
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

        $this->assertSame([], $r1->getAttributes());
        $this->assertSame(['user' => 123], $r2->getAttributes());
        $this->assertSame(['user' => 123, 'role' => 'admin'], $r3->getAttributes());
    }

    public function testGetAttribute(): void
    {
        $r1 = $this->createTestRequest()->withAttribute('user', 123);

        $this->assertSame(123, $r1->getAttribute('user'));
        $this->assertNull($r1->getAttribute('non_existent'));
        $this->assertSame('default', $r1->getAttribute('non_existent', 'default'));
    }

    public function testWithAttribute(): void
    {
        $r1 = $this->createTestRequest();
        $r2 = $r1->withAttribute('user', 123);

        $this->assertNotSame($r1, $r2);
        $this->assertNull($r1->getAttribute('user'));
        $this->assertSame(123, $r2->getAttribute('user'));
    }

    public function testWithoutAttribute(): void
    {
        $r1 = $this->createTestRequest()->withAttribute('user', 123)->withAttribute('role', 'admin');

        $r2 = $r1->withoutAttribute('user');

        $this->assertNotSame($r1, $r2);
        $this->assertSame(['user' => 123, 'role' => 'admin'], $r1->getAttributes());
        $this->assertSame(['role' => 'admin'], $r2->getAttributes());
        $this->assertNull($r2->getAttribute('user'));
    }
}
