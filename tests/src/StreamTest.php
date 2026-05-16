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
            $tempFile = tempnam(directory: sys_get_temp_dir(), prefix: 'wfl_stream_test');
            static::assertIsString($tempFile);
            file_put_contents(filename: $tempFile, data: 'File Content');

            $stream = new Stream($tempFile);
            static::assertSame('File Content', (string) $stream);

            $stream->close();
            unlink($tempFile);
        }

        #[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
        public function testConstructorThrowsExceptionForNonExistentFile(): void
        {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Failed to open stream');

            set_error_handler(static fn(): bool => true);
            try {
                new Stream('/path/to/non/existent/file/' . uniqid());
            } finally {
                restore_error_handler();
            }
        }

        public function testConstructorThrowsExceptionForInvalidType(): void
        {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Invalid stream provided');

            new Stream(12_345);
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
            $file = tempnam(directory: sys_get_temp_dir(), prefix: 'write_only');
            static::assertIsString($file);
            $stream = new Stream($file, 'w');

            static::assertSame('', (string) $stream);

            $stream->close();
            unlink($file);
        }

        public function testDetachReturnsResource(): void
        {
            $resource = fopen(filename: 'php://temp', mode: 'r+');
            static::assertIsResource($resource);
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
            $resource = fopen(filename: 'php://temp', mode: 'r+');
            static::assertIsResource($resource);
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

        public function testSeekThrowsExceptionIfDetached(): void
        {
            $stream = $this->createStream();
            $stream->detach();

            $this->expectException(RuntimeException::class);
            $stream->seek(0);
        }
    }
}
