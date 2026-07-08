<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Waffle\Commons\Http\Factory\StreamFactory;
use Waffle\Commons\Http\UploadedFile;

/**
 * STATE-02: a stream-backed UploadedFile keeps its content in a stream (never a
 * shared temp file) and copies it straight to the destination on moveTo().
 */
#[CoversClass(UploadedFile::class)]
final class UploadedFileStreamTest extends TestCase
{
    public function testGetStreamReturnsTheBackingStream(): void
    {
        $stream = new StreamFactory()->createStream('hello upload');
        $file = new UploadedFile($stream, 12, UPLOAD_ERR_OK, 'h.txt', 'text/plain');

        static::assertSame($stream, $file->getStream());
    }

    public function testMoveToCopiesStreamContentToDestination(): void
    {
        $dest = tempnam(sys_get_temp_dir(), 'wfl_state02_dest');
        if ($dest === false) {
            self::fail('Unable to create a destination temp file.');
        }

        try {
            $file = new UploadedFile(new StreamFactory()->createStream('streamed bytes'), 14, UPLOAD_ERR_OK);
            $file->moveTo($dest);

            static::assertSame('streamed bytes', file_get_contents($dest));
        } finally {
            if (is_file($dest)) {
                unlink($dest);
            }
        }
    }

    public function testGetStreamThrowsAfterStreamBackedMove(): void
    {
        $dest = tempnam(sys_get_temp_dir(), 'wfl_state02_moved');
        if ($dest === false) {
            self::fail('Unable to create a destination temp file.');
        }

        try {
            $file = new UploadedFile(new StreamFactory()->createStream('x'), 1, UPLOAD_ERR_OK);
            $file->moveTo($dest);

            $this->expectException(RuntimeException::class);
            $file->getStream();
        } finally {
            if (is_file($dest)) {
                unlink($dest);
            }
        }
    }
}
