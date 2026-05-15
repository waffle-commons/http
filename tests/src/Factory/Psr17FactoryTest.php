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
        static::assertInstanceOf(ResponseFactoryInterface::class, $factory);

        $response = $factory->createResponse(404, 'Not Found');
        static::assertSame(404, $response->getStatusCode());
        static::assertSame('Not Found', $response->getReasonPhrase());
    }

    public function testServerRequestFactory(): void
    {
        $factory = new ServerRequestFactory();
        static::assertInstanceOf(ServerRequestFactoryInterface::class, $factory);

        $request = $factory->createServerRequest('POST', '/test', ['SERVER_NAME' => 'waffle.io']);
        static::assertSame('POST', $request->getMethod());
        static::assertSame('/test', $request->getUri()->getPath());
        static::assertSame(['SERVER_NAME' => 'waffle.io'], $request->getServerParams());
    }

    public function testStreamFactory(): void
    {
        $factory = new StreamFactory();
        static::assertInstanceOf(StreamFactoryInterface::class, $factory);

        $stream = $factory->createStream('content');
        static::assertSame('content', (string) $stream);

        $file = tempnam(directory: sys_get_temp_dir(), prefix: 'wfl_stream_factory');
        static::assertIsString($file);
        file_put_contents(filename: $file, data: 'file content');
        $streamFile = $factory->createStreamFromFile($file);
        static::assertSame('file content', (string) $streamFile);
        if (is_string($file)) {
            unlink($file);
        }

        $resource = fopen(filename: 'php://temp', mode: 'r+');
        static::assertIsResource($resource);
        $streamRes = $factory->createStreamFromResource($resource);
        static::assertSame($resource, $streamRes->detach());
    }

    public function testUriFactory(): void
    {
        $factory = new UriFactory();
        static::assertInstanceOf(UriFactoryInterface::class, $factory);

        $uri = $factory->createUri('https://example.com/path');
        static::assertSame('https', $uri->getScheme());
        static::assertSame('/path', $uri->getPath());
    }

    public function testUploadedFileFactory(): void
    {
        $factory = new UploadedFileFactory();
        static::assertInstanceOf(UploadedFileFactoryInterface::class, $factory);

        $stream = $this->createStream('upload content');
        $upload = $factory->createUploadedFile($stream, 100, UPLOAD_ERR_OK, 'test.txt', 'text/plain');

        static::assertSame(100, $upload->getSize());
        static::assertSame('test.txt', $upload->getClientFilename());
        static::assertSame('upload content', (string) $upload->getStream());
    }
}
