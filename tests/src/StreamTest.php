<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Http;

use RuntimeException;
use Waffle\Commons\Http\Stream;

class StreamTest extends AbstractTestCase
{
    public function testConstructorInitializesStream(): void
    {
        $stream = $this->createStream('Hello');
        $this->assertSame('Hello', (string) $stream);
        $stream->close();
    }

    public function testToStringReadsContent(): void
    {
        $stream = $this->createStream('Waffle');
        $this->assertSame('Waffle', (string) $stream);
    }

    public function testToStringRewindsStream(): void
    {
        $stream = $this->createStream('Hello');
        $stream->read(5);
        $this->assertSame('Hello', (string) $stream);
    }

    public function testDetachReturnsResource(): void
    {
        $resource = fopen('php://temp', 'r+');
        $stream = new Stream($resource);
        $this->assertSame($resource, $stream->detach());
        $this->assertNull($stream->detach()); // Detached
    }

    public function testCloseRemovesResource(): void
    {
        $stream = $this->createStream('test');
        $stream->close();

        $this->assertFalse($stream->isReadable());
        $this->assertFalse($stream->isWritable());
        $this->assertFalse($stream->isSeekable());
    }

    public function testGetSize(): void
    {
        $stream = $this->createStream('Hello World');
        $this->assertSame(11, $stream->getSize());
    }

    public function testTell(): void
    {
        $stream = $this->createStream('Hello World');
        $stream->read(5);
        $this->assertSame(5, $stream->tell());
    }

    public function testEof(): void
    {
        $stream = $this->createStream('Hi');
        $this->assertFalse($stream->eof());
        $stream->read(2);
        $this->assertTrue($stream->eof());
    }

    public function testSeek(): void
    {
        $stream = $this->createStream('Hello World');
        $stream->seek(6);
        $this->assertSame(6, $stream->tell());
        $this->assertSame('World', $stream->getContents());
    }

    public function testRewind(): void
    {
        $stream = $this->createStream('Hello');
        $stream->seek(5);
        $stream->rewind();
        $this->assertSame(0, $stream->tell());
        $this->assertSame('Hello', (string) $stream);
    }

    public function testWrite(): void
    {
        $stream = $this->createStream();
        $bytesWritten = $stream->write('Hello');
        $this->assertSame(5, $bytesWritten);
        $this->assertSame('Hello', (string) $stream);
    }

    public function testRead(): void
    {
        $stream = $this->createStream('Hello World');
        $content = $stream->read(5);
        $this->assertSame('Hello', $content);
        $this->assertSame(5, $stream->tell());
    }

    public function testGetContents(): void
    {
        $stream = $this->createStream('Waffle Framework');
        $this->assertSame('Waffle Framework', $stream->getContents());
        $this->assertSame('', $stream->getContents()); // Pointer is at the end
    }

    public function testGetMetadata(): void
    {
        $stream = $this->createStream();
        $this->assertIsArray($stream->getMetadata());
        $this->assertSame('php://temp', $stream->getMetadata('uri'));
        $this->assertNull($stream->getMetadata('non_existent_key'));
    }

    public function testThrowsExceptionOnDetachedStream(): void
    {
        $this->expectException(RuntimeException::class);
        $stream = $this->createStream('test');
        $stream->detach();
        $stream->read(1); // Should fail
    }
}
