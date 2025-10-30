<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Http\Factory;

use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;
use Waffle\Commons\Http\Factory\RequestFactory;
use Waffle\Commons\Http\UploadedFile;
use WaffleTests\Commons\Http\AbstractTestCase;

class RequestFactoryTest extends AbstractTestCase
{
    private array $serverBackup;
    private array $getBackup;
    private array $postBackup;
    private array $cookieBackup;
    private array $filesBackup;

    protected function setUp(): void
    {
        // Backup superglobals
        $this->serverBackup = $_SERVER;
        $this->getBackup = $_GET;
        $this->postBackup = $_POST;
        $this->cookieBackup = $_COOKIE;
        $this->filesBackup = $_FILES;
    }

    protected function tearDown(): void
    {
        // Restore superglobals
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
                'HTTP_HOST' => 'localhost',
            ];
        $_GET = $get;
        $_POST = $post;
        $_COOKIE = $cookie;
        $_FILES = $files;
    }

    public function testCreateFromGlobalsBasic(): void
    {
        $this->setGlobals(
            server: [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/test?foo=bar',
                'QUERY_STRING' => 'foo=bar',
                'HTTP_HOST' => 'example.com',
                'HTTP_CONTENT_TYPE' => 'application/json',
                'HTTP_X_TEST' => 'Waffle',
            ],
            get: ['foo' => 'bar'],
            cookie: ['user' => 'test'],
        );

        $factory = new RequestFactory();
        $request = $factory->createFromGlobals();

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('http://example.com/test?foo=bar', (string) $request->getUri());
        $this->assertSame(['foo' => 'bar'], $request->getQueryParams());
        $this->assertSame(['user' => 'test'], $request->getCookieParams());
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertSame('Waffle', $request->getHeaderLine('X-Test'));
        $this->assertSame('example.com', $request->getHeaderLine('Host'));
    }

    public function testCreateFromGlobalsThrowsExceptionForInvalidFiles(): void
    {
        $this->setGlobals(
            files: ['invalid_upload' => 'this is just a string, not an array']
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value in $_FILES array');

        $factory = new RequestFactory();
        $factory->createFromGlobals();
    }

    public function testHttpsDetection(): void
    {
        $this->setGlobals(server: ['HTTPS' => 'on', 'HTTP_HOST' => 'secure.com']);
        $request = new RequestFactory()->createFromGlobals();
        $this->assertSame('https', $request->getUri()->getScheme());

        $this->setGlobals(server: ['HTTPS' => '1', 'HTTP_HOST' => 'secure.com']);
        $request = new RequestFactory()->createFromGlobals();
        $this->assertSame('https', $request->getUri()->getScheme());

        $this->setGlobals(server: ['HTTPS' => 'off', 'HTTP_HOST' => 'unsecure.com']);
        $request = new RequestFactory()->createFromGlobals();
        $this->assertSame('http', $request->getUri()->getScheme());
    }

    public function testParsedBodyForJson(): void
    {
        $this->setGlobals(
            server: ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json'],
            post: ['user' => 'waffle'], // Ignored for JSON
        );

        // Simulates php://input
        $factory = new RequestFactory(fn() => $this->createStream('{"user":"test"}'));
        $request = $factory->createFromGlobals();

        $this->assertSame(['user' => 'test'], $request->getParsedBody());
    }

    public function testParsedBodyForUrlEncoded(): void
    {
        $this->setGlobals(
            server: ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            post: ['user' => 'waffle'],
        );

        $request = new RequestFactory()->createFromGlobals();
        $this->assertSame(['user' => 'waffle'], $request->getParsedBody());
    }

    public function testParsedBodyForFormData(): void
    {
        $this->setGlobals(
            server: ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'multipart/form-data'],
            post: ['user' => 'waffle'],
        );

        $request = new RequestFactory()->createFromGlobals();
        $this->assertSame(['user' => 'waffle'], $request->getParsedBody());
    }

    public function testUploadedFiles(): void
    {
        // Simulates file upload
        $tempFile = tempnam(sys_get_temp_dir(), 'wfl_test_upload');
        if ($tempFile === false) {
            $this->fail('Unable to create temporary file');
        }
        file_put_contents($tempFile, 'test');

        $files = [
            'avatar' => [
                'name' => 'test.txt',
                'type' => 'text/plain',
                'tmp_name' => $tempFile,
                'error' => UPLOAD_ERR_OK,
                'size' => 4,
            ],
        ];
        $this->setGlobals(files: $files);

        $request = new RequestFactory()->createFromGlobals();
        $uploadedFiles = $request->getUploadedFiles();

        $this->assertCount(1, $uploadedFiles);
        $this->assertInstanceOf(UploadedFile::class, $uploadedFiles['avatar']);
        $this->assertSame('test.txt', $uploadedFiles['avatar']->getClientFilename());
        $this->assertSame(4, $uploadedFiles['avatar']->getSize());

        unlink($tempFile);
    }

    public function testUploadedFilesNested(): void
    {
        // Simulates nested file uploads
        $tempFile1 = tempnam(sys_get_temp_dir(), 'wfl_test_upload1');
        file_put_contents($tempFile1, 'file1');
        $tempFile2 = tempnam(sys_get_temp_dir(), 'wfl_test_upload2');
        file_put_contents($tempFile2, 'file2');

        $files = [
            'docs' => [
                'name' => ['doc1.txt', 'doc2.txt'],
                'type' => ['text/plain', 'text/plain'],
                'tmp_name' => [$tempFile1, $tempFile2],
                'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
                'size' => [5, 5],
            ],
        ];
        $this->setGlobals(files: $files);

        $request = new RequestFactory()->createFromGlobals();
        $uploadedFiles = $request->getUploadedFiles();

        $this->assertCount(1, $uploadedFiles);
        $this->assertIsArray($uploadedFiles['docs']);
        $this->assertCount(2, $uploadedFiles['docs']);
        $this->assertInstanceOf(UploadedFileInterface::class, $uploadedFiles['docs'][0]);
        $this->assertInstanceOf(UploadedFileInterface::class, $uploadedFiles['docs'][1]);
        $this->assertSame('doc1.txt', $uploadedFiles['docs'][0]->getClientFilename());
        $this->assertSame('doc2.txt', $uploadedFiles['docs'][1]->getClientFilename());

        unlink($tempFile1);
        unlink($tempFile2);
    }

    public function testAuthorizationHeader(): void
    {
        $this->setGlobals(server: ['HTTP_AUTHORIZATION' => 'Bearer 12345']);
        $request = new RequestFactory()->createFromGlobals();
        $this->assertSame('Bearer 12345', $request->getHeaderLine('Authorization'));
    }

    public function testRedirectAuthorizationHeader(): void
    {
        // Tests REDIRECT_HTTP_AUTHORIZATION (used by Apache + mod_rewrite)
        $this->setGlobals(server: ['REDIRECT_HTTP_AUTHORIZATION' => 'Bearer 67890']);
        $request = new RequestFactory()->createFromGlobals();
        $this->assertSame('Bearer 67890', $request->getHeaderLine('Authorization'));
    }

    public function testBasicAuthCredentials(): void
    {
        $this->setGlobals(server: [
            'PHP_AUTH_USER' => 'waffle',
            'PHP_AUTH_PW' => 'secret',
        ]);
        $request = new RequestFactory()->createFromGlobals();
        $this->assertSame('waffle:secret', $request->getUri()->getUserInfo());
        $this->assertSame('Basic ' . base64_encode('waffle:secret'), $request->getHeaderLine('Authorization'));
    }

    public function testUriWithPort(): void
    {
        $this->setGlobals(server: [
            'HTTP_HOST' => 'example.com:8080',
            'SERVER_PORT' => 8080,
        ]);
        $request = new RequestFactory()->createFromGlobals();
        $this->assertSame('http://example.com:8080/', (string) $request->getUri());
        $this->assertSame(8080, $request->getUri()->getPort());
    }

    public function testUriWithQueryStringInRequestUri(): void
    {
        $this->setGlobals(server: [
            'REQUEST_URI' => '/path?foo=bar&baz=qux',
            'QUERY_STRING' => 'foo=bar&baz=qux',
        ]);
        $request = new RequestFactory()->createFromGlobals();
        $this->assertSame('/path', $request->getUri()->getPath());
        $this->assertSame('foo=bar&baz=qux', $request->getUri()->getQuery());
    }
}
