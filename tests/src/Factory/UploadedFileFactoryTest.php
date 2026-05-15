<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Http\Factory;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Waffle\Commons\Http\Factory\UploadedFileFactory;

class UploadedFileFactoryTest extends TestCase
{
    public function testCreateUploadedFileCalculatesSizeIfNull(): void
    {
        $factory = new UploadedFileFactory();
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('getSize')->willReturn(12_345);
        $stream->method('getMetadata')->willReturn('php://temp');
        $stream->method('__toString')->willReturn('content');

        $file = $factory->createUploadedFile($stream);

        static::assertSame(12_345, $file->getSize());
    }

    public function testCreateUploadedFileUsesStreamPathIfRealFile(): void
    {
        $factory = new UploadedFileFactory();

        $tmpFile = tempnam(directory: sys_get_temp_dir(), prefix: 'test_upload');
        static::assertIsString($tmpFile);
        file_put_contents(filename: $tmpFile, data: 'test content');

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('getSize')->willReturn(12);
        $stream->method('getMetadata')->willReturn($tmpFile);

        $file = $factory->createUploadedFile($stream);

        // We can't easily check the path as it's private in UploadedFile
        // But we can verify no exception is thrown and it works.
        static::assertSame(12, $file->getSize());

        unlink($tmpFile);
    }
}
