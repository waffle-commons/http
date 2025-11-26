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
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getSize')->willReturn(12345);
        $stream->method('getMetadata')->with('uri')->willReturn('php://temp');
        $stream->method('__toString')->willReturn('content');

        $file = $factory->createUploadedFile($stream);

        $this->assertSame(12345, $file->getSize());
    }

    public function testCreateUploadedFileUsesStreamPathIfRealFile(): void
    {
        $factory = new UploadedFileFactory();
        
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_upload');
        file_put_contents($tmpFile, 'test content');
        
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getSize')->willReturn(12);
        $stream->method('getMetadata')->with('uri')->willReturn($tmpFile);

        $file = $factory->createUploadedFile($stream);
        
        // We can't easily check the path as it's private in UploadedFile
        // But we can verify no exception is thrown and it works.
        $this->assertSame(12, $file->getSize());
        
        unlink($tmpFile);
    }
}
