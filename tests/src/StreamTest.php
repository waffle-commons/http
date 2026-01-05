<?php

declare(strict_types=1);

// 1. Define mocks in the target namespace to override built-in functions
namespace Waffle\Commons\Http {
    use WaffleTests\Commons\Http\StreamTest;

    function fseek($stream, int $offset, int $whence = SEEK_SET): int
    {
        if (StreamTest::$mockFseekFail) {
            return -1; // Simulate failure
        }
        return \fseek($stream, $offset, $whence);
    }

    function ftell($stream): int|false
    {
        if (StreamTest::$mockFtellFail) {
            return false; // Simulate failure
        }
        return \ftell($stream);
    }

    function fwrite($stream, string $data, ?int $length = null): int|false
    {
        if (StreamTest::$mockFwriteFail) {
            return false; // Simulate failure
        }
        return \fwrite($stream, $data, $length);
    }

    function fread($stream, int $length): string|false
    {
        if (StreamTest::$mockFreadFail) {
            return false; // Simulate failure
        }
        return \fread($stream, $length);
    }

    function stream_get_contents($stream, ?int $length = null, int $offset = -1): string|false
    {
        if (StreamTest::$mockStreamGetContentsFail) {
            return false; // Simulate failure
        }
        return \stream_get_contents($stream, $length, $offset);
    }
}

// 2. Define the test class
namespace WaffleTests\Commons\Http {
    use InvalidArgumentException;
    use RuntimeException;
    use Waffle\Commons\Http\Stream;

    class StreamTest extends AbstractTestCase
    {
        // Flags to control mocks
        public static bool $mockFseekFail = false;
        public static bool $mockFtellFail = false;
        public static bool $mockFwriteFail = false;
        public static bool $mockFreadFail = false;
        public static bool $mockStreamGetContentsFail = false;

        #[\Override]
        protected function setUp(): void
        {
            parent::setUp();
            // Reset flags before each test
            self::$mockFseekFail = false;
            self::$mockFtellFail = false;
            self::$mockFwriteFail = false;
            self::$mockFreadFail = false;
            self::$mockStreamGetContentsFail = false;
        }

        public function testConstructorInitializesStreamWithResource(): void
        {
            $stream = $this->createStream('Hello');
            static::assertSame('Hello', (string) $stream);
            $stream->close();
        }

        public function testConstructorInitializesStreamWithFilePath(): void
        {
            $tempFile = tempnam(sys_get_temp_dir(), 'wfl_stream_test');
            file_put_contents($tempFile, 'File Content');

            $stream = new Stream($tempFile);
            static::assertSame('File Content', (string) $stream);

            $stream->close();
            unlink($tempFile);
        }

        public function testConstructorThrowsExceptionForNonExistentFile(): void
        {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Failed to open stream');

            new Stream('/path/to/non/existent/file/' . uniqid());
        }

        public function testConstructorThrowsExceptionForInvalidType(): void
        {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Invalid stream provided');

            new Stream(12345);
        }

        public function testToStringReadsContent(): void
        {
            $stream = $this->createStream('Waffle');
            static::assertSame('Waffle', (string) $stream);
        }

        public function testToStringReturnsEmptyStringOnException(): void
        {
            $stream = $this->createStream('Content');
            $stream->close(); // Force exception by closing resource

            static::assertSame('', (string) $stream);
        }

        public function testToStringReturnsEmptyStringOnRewindFailure(): void
        {
            $stream = $this->createStream('Content');
            // Simulate failure of fseek (called by rewind)
            self::$mockFseekFail = true;

            // __toString catches RuntimeException thrown by rewind/seek
            static::assertSame('', (string) $stream);
        }

        public function testToStringRewindsStream(): void
        {
            $stream = $this->createStream('Hello');
            $stream->read(5);
            static::assertSame('Hello', (string) $stream);
        }

        public function testToStringReturnsEmptyStringIfNotReadable(): void
        {
            $file = tempnam(sys_get_temp_dir(), 'write_only');
            $stream = new Stream($file, 'w');

            static::assertSame('', (string) $stream);

            $stream->close();
            unlink($file);
        }

        public function testDetachReturnsResource(): void
        {
            $resource = fopen('php://temp', 'r+');
            $stream = new Stream($resource);
            static::assertSame($resource, $stream->detach());
            static::assertNull($stream->detach());
        }

        public function testCloseRemovesResource(): void
        {
            $stream = $this->createStream('test');
            $stream->close();

            static::assertFalse($stream->isReadable());
            static::assertFalse($stream->isWritable());
            static::assertFalse($stream->isSeekable());
        }

        public function testGetSize(): void
        {
            $stream = $this->createStream('Hello World');
            static::assertSame(11, $stream->getSize());
        }

        public function testGetSizeReturnsNullIfDetached(): void
        {
            $resource = fopen('php://temp', 'r+');
            $stream = new Stream($resource);
            $stream->detach();
            static::assertNull($stream->getSize());
        }

        public function testTell(): void
        {
            $stream = $this->createStream('Hello World');
            $stream->read(5);
            static::assertSame(5, $stream->tell());
        }

        public function testTellThrowsExceptionIfDetached(): void
        {
            $stream = $this->createStream();
            $stream->detach();

            $this->expectException(RuntimeException::class);
            $stream->tell();
        }

        public function testTellThrowsExceptionOnFailure(): void
        {
            $stream = $this->createStream();
            self::$mockFtellFail = true;

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Unable to retrieve stream position');
            $stream->tell();
        }

        public function testEof(): void
        {
            $stream = $this->createStream('Hi');
            static::assertFalse($stream->eof());
            $stream->read(2);
            static::assertFalse($stream->eof());
            $stream->read(1);
            static::assertTrue($stream->eof());
        }

        public function testSeek(): void
        {
            $stream = $this->createStream('Hello World');
            $stream->seek(6);
            static::assertSame(6, $stream->tell());
            static::assertSame('World', $stream->getContents());
        }

        public function testSeekThrowsExceptionIfDetached(): void
        {
            $stream = $this->createStream();
            $stream->detach();

            $this->expectException(RuntimeException::class);
            $stream->seek(0);
        }

        public function testSeekThrowsExceptionForNonSeekableStream(): void
        {
            $stream = new Stream(fopen('php://output', 'w'));

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Stream is not seekable');

            $stream->seek(0);
        }

        public function testSeekThrowsExceptionOnFailure(): void
        {
            $stream = $this->createStream();
            self::$mockFseekFail = true;

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
            $file = tempnam(sys_get_temp_dir(), 'readonly');
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
            self::$mockFwriteFail = true;

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
            $file = tempnam(sys_get_temp_dir(), 'writeonly');
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
            self::$mockFreadFail = true;

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
            $file = tempnam(sys_get_temp_dir(), 'writeonly_contents');
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
            self::$mockStreamGetContentsFail = true;

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
}
