<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Waffle\Commons\Http\Stream;

/**
 * Targets specific edge cases in Stream implementation:
 * - Operations on closed/detached streams
 * - Error handling during string casting
 */
#[CoversClass(Stream::class)]
class StreamEdgeCaseTest extends TestCase
{
    public function testToStringReturnsEmptyStringOnException(): void
    {
        // Scenario: The underlying resource is closed, but __toString() is called.
        // PSR-7 states __toString must NOT throw an exception but return an empty string.

        $resource = fopen('php://memory', 'r+');
        $stream = new Stream($resource);
        $stream->close(); // We manually close it

        // Attempting to read from a closed stream inside __toString should catch the exception
        // and return ""
        $this->assertSame('', (string) $stream);
    }

    public function testDetachReturnsNullIfAlreadyDetached(): void
    {
        $resource = fopen('php://memory', 'r+');
        $stream = new Stream($resource);

        // First detach returns the resource
        $this->assertIsResource($stream->detach());

        // Second detach should return null (idempotency check)
        $this->assertNull($stream->detach());
    }

    public function testSeekThrowsExceptionOnClosedStream(): void
    {
        $resource = fopen('php://memory', 'r+');
        $stream = new Stream($resource);
        $stream->close();

        $this->expectException(RuntimeException::class);
        $stream->seek(0);
    }

    public function testWriteThrowsExceptionOnClosedStream(): void
    {
        $resource = fopen('php://memory', 'r+');
        $stream = new Stream($resource);
        $stream->close();

        $this->expectException(RuntimeException::class);
        $stream->write('test');
    }

    public function testReadThrowsExceptionOnClosedStream(): void
    {
        $resource = fopen('php://memory', 'r+');
        $stream = new Stream($resource);
        $stream->close();

        $this->expectException(RuntimeException::class);
        $stream->read(10);
    }

    public function testGetContentsThrowsExceptionOnClosedStream(): void
    {
        $resource = fopen('php://memory', 'r+');
        $stream = new Stream($resource);
        $stream->close();

        $this->expectException(RuntimeException::class);
        $stream->getContents();
    }

    public function testGetSizeReturnsNullIfResourceClosed(): void
    {
        $resource = fopen('php://memory', 'r+');
        $stream = new Stream($resource);
        $stream->close();

        $this->assertNull($stream->getSize());
    }
}
