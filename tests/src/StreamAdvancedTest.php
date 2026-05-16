<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Http;

use RuntimeException;
use Waffle\Commons\Http\Stream;

/**
 * Advanced tests for Stream - seeks, reads, writes, content retrieval.
 * Relies on mock functions defined in StreamTest.php namespace block.
 */
class StreamAdvancedTest extends AbstractTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        // Reset mock flags (defined in StreamTest static properties)
        StreamTest::$mockFseekFail = false;
        StreamTest::$mockFtellFail = false;
        StreamTest::$mockFwriteFail = false;
        StreamTest::$mockFreadFail = false;
        StreamTest::$mockStreamGetContentsFail = false;
    }

    public function testSeek(): void
    {
        $stream = $this->createStream('Hello World');
        $stream->seek(6);
        static::assertSame(6, $stream->tell());
        static::assertSame('World', $stream->getContents());
    }

    public function testSeekThrowsExceptionForNonSeekableStream(): void
    {
        $stream = new Stream(fopen(filename: 'php://output', mode: 'w'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stream is not seekable');

        $stream->seek(0);
    }

    public function testSeekThrowsExceptionOnFailure(): void
    {
        $stream = $this->createStream();
        StreamTest::$mockFseekFail = true;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to seek to stream position');
        $stream->seek(0);
    }

    public function testRewind(): void
    {
        $stream = $this->createStream('Hello');
        $stream->seek(5);
        $stream->rewind();
        static::assertSame(0, $stream->tell());
        static::assertSame('Hello', (string) $stream);
    }

    public function testWrite(): void
    {
        $stream = $this->createStream();
        $bytesWritten = $stream->write('Hello');
        static::assertSame(5, $bytesWritten);
        static::assertSame('Hello', (string) $stream);
    }

    public function testWriteThrowsExceptionIfNotWritable(): void
    {
        $file = tempnam(directory: sys_get_temp_dir(), prefix: 'readonly');
        static::assertIsString($file);
        $stream = new Stream($file, 'r'); // Read-only mode

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stream is not writable');

        try {
            $stream->write('fail');
        } finally {
            $stream->close();
            unlink($file);
        }
    }

    public function testWriteThrowsExceptionOnFailure(): void
    {
        $stream = $this->createStream();
        StreamTest::$mockFwriteFail = true;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to write to stream');
        $stream->write('fail');
    }

    public function testRead(): void
    {
        $stream = $this->createStream('Hello World');
        $content = $stream->read(5);
        static::assertSame('Hello', $content);
        static::assertSame(5, $stream->tell());
    }

    public function testReadThrowsExceptionIfDetached(): void
    {
        $stream = $this->createStream();
        $stream->detach();

        $this->expectException(RuntimeException::class);
        $stream->read(1);
    }

    public function testReadThrowsExceptionIfNotReadable(): void
    {
        $file = tempnam(directory: sys_get_temp_dir(), prefix: 'writeonly');
        static::assertIsString($file);
        $stream = new Stream($file, 'w'); // Write-only

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stream is not readable');

        try {
            $stream->read(1);
        } finally {
            $stream->close();
            unlink($file);
        }
    }

    public function testReadThrowsExceptionOnFailure(): void
    {
        $stream = $this->createStream('data');
        StreamTest::$mockFreadFail = true;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to read from stream');
        $stream->read(10);
    }

    public function testGetContents(): void
    {
        $stream = $this->createStream('Waffle Framework');
        static::assertSame('Waffle Framework', $stream->getContents());
        static::assertSame('', $stream->getContents());
    }

    public function testGetContentsThrowsExceptionIfNotReadable(): void
    {
        $file = tempnam(directory: sys_get_temp_dir(), prefix: 'writeonly_contents');
        static::assertIsString($file);
        $stream = new Stream($file, 'w');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stream is not readable');

        try {
            $stream->getContents();
        } finally {
            $stream->close();
            unlink($file);
        }
    }

    public function testGetContentsThrowsExceptionOnFailure(): void
    {
        $stream = $this->createStream('data');
        StreamTest::$mockStreamGetContentsFail = true;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to read stream contents');
        $stream->getContents();
    }

    public function testGetMetadata(): void
    {
        $stream = $this->createStream();
        static::assertIsArray($stream->getMetadata());
        static::assertSame('php://temp', $stream->getMetadata('uri'));
        static::assertNull($stream->getMetadata('non_existent_key'));

        // Test detached metadata retrieval
        $stream->detach();
        static::assertSame('php://temp', $stream->getMetadata('uri'));
    }

    public function testThrowsExceptionOnDetachedStream(): void
    {
        $this->expectException(RuntimeException::class);
        $stream = $this->createStream('test');
        $stream->detach();
        $stream->read(1);
    }
}
