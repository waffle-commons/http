<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Http\Factory;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Waffle\Commons\Http\Factory\GlobalsFactory;

use WaffleTests\Commons\Http\AbstractTestCase;

class GlobalsFactoryTest extends AbstractTestCase
{
    private array $serverBackup;
    private array $getBackup;
    private array $postBackup;
    private array $cookieBackup;
    private array $filesBackup;

    #[\Override]
    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->getBackup = $_GET;
        $this->postBackup = $_POST;
        $this->cookieBackup = $_COOKIE;
        $this->filesBackup = $_FILES;
    }

    #[\Override]
    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_GET = $this->getBackup;
        $_POST = $this->postBackup;
        $_COOKIE = $this->cookieBackup;
        $_FILES = $this->filesBackup;
    }

    private function setGlobals(
        array $server = [],
        array $get = [],
        array $post = [],
        array $cookie = [],
        array $files = [],
    ): void {
        $_SERVER = $server
        + [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST' => 'example.com',
        ];
        $_GET = $get;
        $_POST = $post;
        $_COOKIE = $cookie;
        $_FILES = $files;
    }

    public function testCreateFromGlobals(): void
    {
        $this->setGlobals();
        $factory = new GlobalsFactory();
        $request = $factory->createFromGlobals();

        static::assertSame('GET', $request->getMethod());
        static::assertSame('http://example.com/', (string) $request->getUri());
        static::assertSame('1.1', $request->getProtocolVersion());
    }

    public function testCreateWithCustomBodyStreamFactory(): void
    {
        $this->setGlobals();
        $stream = $this->createStub(StreamInterface::class);

        // Test dependency injection for the body stream factory
        $factory = new GlobalsFactory(bodyStreamFactory: static fn() => $stream);
        $request = $factory->createFromGlobals();

        static::assertSame($stream, $request->getBody());
    }

    public function testAuthorizationHeaderFromGlobals(): void
    {
        $this->setGlobals(server: ['HTTP_AUTHORIZATION' => 'Bearer 12345']);
        $request = new GlobalsFactory()->createFromGlobals();
        static::assertSame('Bearer 12345', $request->getHeaderLine('Authorization'));
    }

    public function testAuthorizationHeaderFromRedirectGlobals(): void
    {
        $this->setGlobals(server: ['REDIRECT_HTTP_AUTHORIZATION' => 'Bearer 67890']);
        $request = new GlobalsFactory()->createFromGlobals();
        static::assertSame('Bearer 67890', $request->getHeaderLine('Authorization'));
    }

    public function testBasicAuthCredentials(): void
    {
        $this->setGlobals(server: [
            'PHP_AUTH_USER' => 'waffle',
            'PHP_AUTH_PW' => 'secret',
        ]);
        $request = new GlobalsFactory()->createFromGlobals();
        static::assertSame('waffle:secret', $request->getUri()->getUserInfo());
        static::assertSame('Basic ' . base64_encode('waffle:secret'), $request->getHeaderLine('Authorization'));
    }

    public function testUriWithPort(): void
    {
        $this->setGlobals(server: [
            'HTTP_HOST' => 'example.com:8080',
            'SERVER_PORT' => 8080,
        ]);
        $request = new GlobalsFactory()->createFromGlobals();
        static::assertSame('http://example.com:8080/', (string) $request->getUri());
        static::assertSame(8080, $request->getUri()->getPort());
    }

    public function testUriWithQueryStringInRequestUri(): void
    {
        $this->setGlobals(server: [
            'REQUEST_URI' => '/path?foo=bar&baz=qux',
            'QUERY_STRING' => 'foo=bar&baz=qux',
        ]);
        $request = new GlobalsFactory()->createFromGlobals();
        static::assertSame('http://example.com/path?foo=bar&baz=qux', (string) $request->getUri());
        static::assertSame('foo=bar&baz=qux', $request->getUri()->getQuery());
    }

    public function testParsedBodyFromPost(): void
    {
        $this->setGlobals(server: [
            'REQUEST_METHOD' => 'POST',
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ], post: ['foo' => 'bar']);
        $request = new GlobalsFactory()->createFromGlobals();
        static::assertSame(['foo' => 'bar'], $request->getParsedBody());
    }

    public function testCookieParams(): void
    {
        $this->setGlobals(cookie: ['theme' => 'dark', 'session_id' => '123']);
        $request = new GlobalsFactory()->createFromGlobals();

        static::assertSame(['theme' => 'dark', 'session_id' => '123'], $request->getCookieParams());
    }

    public function testServerParams(): void
    {
        $this->setGlobals(server: ['CUSTOM_SERVER_VAR' => 'waffle_test']);
        $request = new GlobalsFactory()->createFromGlobals();

        $serverParams = $request->getServerParams();
        static::assertArrayHasKey('CUSTOM_SERVER_VAR', $serverParams);
        static::assertSame('waffle_test', $serverParams['CUSTOM_SERVER_VAR']);
    }

    public function testProtocolVersionParsing(): void
    {
        $this->setGlobals(server: ['SERVER_PROTOCOL' => 'HTTP/2.0']);
        $request = new GlobalsFactory()->createFromGlobals();
        static::assertSame('2.0', $request->getProtocolVersion());

        $this->setGlobals(server: ['SERVER_PROTOCOL' => 'HTTP/1.0']);
        $request = new GlobalsFactory()->createFromGlobals();
        static::assertSame('1.0', $request->getProtocolVersion());
    }

    public function testCustomHeadersParsing(): void
    {
        $this->setGlobals(server: [
            'HTTP_X_CUSTOM_HEADER' => 'custom-value',
            'HTTP_CONTENT_TYPE' => 'application/json', // Should be ignored/handled as Content-Type
        ]);
        $request = new GlobalsFactory()->createFromGlobals();

        static::assertSame('custom-value', $request->getHeaderLine('X-Custom-Header'));
    }

    public function testUploadedFiles(): void
    {
        $this->setGlobals(server: ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'multipart/form-data'], files: [
            'file' => [
                'name' => 'test.txt',
                'type' => 'text/plain',
                'tmp_name' => '/tmp/phpYcfZnq',
                'error' => 0,
                'size' => 123,
            ],
        ]);
        $request = new GlobalsFactory()->createFromGlobals();
        $files = $request->getUploadedFiles();

        static::assertArrayHasKey('file', $files);
        static::assertInstanceOf(UploadedFileInterface::class, $files['file']);
        static::assertSame('test.txt', $files['file']->getClientFilename());
    }

    public function testNestedUploadedFiles(): void
    {
        $this->setGlobals(server: ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'multipart/form-data'], files: [
            'files' => [
                'name' => ['a.txt', 'b.txt'],
                'type' => ['text/plain', 'text/plain'],
                'tmp_name' => ['/tmp/php1', '/tmp/php2'],
                'error' => [0, 0],
                'size' => [10, 20],
            ],
        ]);
        $request = new GlobalsFactory()->createFromGlobals();
        $files = $request->getUploadedFiles();

        static::assertArrayHasKey('files', $files);
        static::assertIsArray($files['files']);
        static::assertCount(2, $files['files']);
        static::assertInstanceOf(UploadedFileInterface::class, $files['files'][0]);
        static::assertSame('a.txt', $files['files'][0]->getClientFilename());
    }

    public function testInvalidFilesStructureThrowsException(): void
    {
        $this->setGlobals(server: ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'multipart/form-data'], files: [
            'invalid_upload' => 'not_an_array', // Invalid structure
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value in $_FILES array.');

        new GlobalsFactory()->createFromGlobals();
    }

    // --- Trusted Hosts Tests ---

    public function testItAcceptsTrustedHost(): void
    {
        $this->setGlobals(server: ['HTTP_HOST' => 'trusted.com']);

        // We explicitly allow trusted.com
        $factory = new GlobalsFactory(trustedHosts: ['trusted.com']);
        $request = $factory->createFromGlobals();

        static::assertSame('trusted.com', $request->getUri()->getHost());
    }

    public function testItAcceptsTrustedHostWithPort(): void
    {
        $this->setGlobals(server: ['HTTP_HOST' => 'trusted.com:8080']);

        // Logic should strip port before checking
        $factory = new GlobalsFactory(trustedHosts: ['trusted.com']);
        $request = $factory->createFromGlobals();

        static::assertSame('trusted.com', $request->getUri()->getHost());
        static::assertSame(8080, $request->getUri()->getPort());
    }

    public function testItRejectsUntrustedHost(): void
    {
        $this->setGlobals(server: ['HTTP_HOST' => 'evil.com']);

        $factory = new GlobalsFactory(trustedHosts: ['trusted.com', 'localhost']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Untrusted Host "evil.com"');

        $factory->createFromGlobals();
    }

    public function testItRejectsMissingHostHeaderWhenTrustedHostsEnabled(): void
    {
        // Simulate HTTP/1.0 request without Host header
        $server = ['REQUEST_METHOD' => 'GET'];
        $_SERVER = $server;

        $factory = new GlobalsFactory(trustedHosts: ['trusted.com']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing Host header');

        $factory->createFromGlobals();
    }

    public function testItAllowsAnyHostIfTrustedListIsEmpty(): void
    {
        $this->setGlobals(server: ['HTTP_HOST' => 'anything.com']);

        // Default behavior (empty list) -> No check
        $factory = new GlobalsFactory(trustedHosts: []);
        $request = $factory->createFromGlobals();

        static::assertSame('anything.com', $request->getUri()->getHost());
    }
}
