<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Http\Factory;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Waffle\Commons\Http\Factory\ResponseFactory;
use Waffle\Commons\Http\Factory\ServerRequestFactory;
use Waffle\Commons\Http\Factory\StreamFactory;
use Waffle\Commons\Http\Factory\UploadedFileFactory;
use Waffle\Commons\Http\Factory\UriFactory;
use WaffleTests\Commons\Http\AbstractTestCase;

class Psr17FactoryTest extends AbstractTestCase
{
    public function testResponseFactory(): void
    {
        $factory = new ResponseFactory();
        $this->assertInstanceOf(ResponseFactoryInterface::class, $factory);

        $response = $factory->createResponse(404, 'Not Found');
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Not Found', $response->getReasonPhrase());
    }

    public function testServerRequestFactory(): void
    {
        $factory = new ServerRequestFactory();
        $this->assertInstanceOf(ServerRequestFactoryInterface::class, $factory);

        $request = $factory->createServerRequest('POST', '/test', ['SERVER_NAME' => 'waffle.io']);
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/test', $request->getUri()->getPath());
        $this->assertSame(['SERVER_NAME' => 'waffle.io'], $request->getServerParams());
    }

    public function testStreamFactory(): void
    {
        $factory = new StreamFactory();
        $this->assertInstanceOf(StreamFactoryInterface::class, $factory);

        $stream = $factory->createStream('content');
        $this->assertSame('content', (string) $stream);

        $file = tempnam(sys_get_temp_dir(), 'wfl_stream_factory');
        file_put_contents($file, 'file content');
        $streamFile = $factory->createStreamFromFile($file);
        $this->assertSame('file content', (string) $streamFile);
        unlink($file);

        $resource = fopen('php://temp', 'r+');
        $streamRes = $factory->createStreamFromResource($resource);
        $this->assertSame($resource, $streamRes->detach());
    }

    public function testUriFactory(): void
    {
        $factory = new UriFactory();
        $this->assertInstanceOf(UriFactoryInterface::class, $factory);

        $uri = $factory->createUri('https://example.com/path');
        $this->assertSame('https', $uri->getScheme());
        $this->assertSame('/path', $uri->getPath());
    }

    public function testUploadedFileFactory(): void
    {
        $factory = new UploadedFileFactory();
        $this->assertInstanceOf(UploadedFileFactoryInterface::class, $factory);

        $stream = $this->createStream('upload content');
        $upload = $factory->createUploadedFile($stream, 100, UPLOAD_ERR_OK, 'test.txt', 'text/plain');

        $this->assertSame(100, $upload->getSize());
        $this->assertSame('test.txt', $upload->getClientFilename());
        $this->assertSame('upload content', (string) $upload->getStream());
    }
}
