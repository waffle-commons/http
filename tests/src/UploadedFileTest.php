<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Http;

use InvalidArgumentException;
use RuntimeException;
use Waffle\Commons\Http\Stream;
use Waffle\Commons\Http\UploadedFile;

class UploadedFileTest extends AbstractTestCase
{
    private string $tempFile;
    private Stream $stream;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'wfl_upload_test');
        file_put_contents($this->tempFile, 'Test content');
        $this->stream = new Stream($this->tempFile, 'r');
    }

    protected function tearDown(): void
    {
        $this->stream->close();
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testConstructor(): void
    {
        $file = new UploadedFile($this->stream, $this->stream->getSize(), UPLOAD_ERR_OK, 'test.txt', 'text/plain');

        $this->assertSame($this->stream, $file->getStream());
        $this->assertSame(12, $file->getSize());
        $this->assertSame(UPLOAD_ERR_OK, $file->getError());
        $this->assertSame('test.txt', $file->getClientFilename());
        $this->assertSame('text/plain', $file->getClientMediaType());
    }

    public function testConstructorWithResource(): void
    {
        $resource = fopen($this->tempFile, 'r');
        $file = new UploadedFile($resource, 12, UPLOAD_ERR_OK);
        $this->assertInstanceOf(Stream::class, $file->getStream());
        fclose($resource); // Close original handle
        $file->getStream()->close(); // Close stream's handle
    }

    public function testConstructorWithString(): void
    {
        $file = new UploadedFile($this->tempFile, 12, UPLOAD_ERR_OK); // path as string
        $this->assertInstanceOf(Stream::class, $file->getStream());
        $this->assertSame('Test content', (string) $file->getStream());
    }

    public function testInvalidStreamOrFileThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UploadedFile(new \stdClass(), 0, UPLOAD_ERR_OK); // Invalid type
    }

    public function testGetStream(): void
    {
        $file = new UploadedFile($this->stream, 12, UPLOAD_ERR_OK);
        $this->assertSame($this->stream, $file->getStream());
    }

    public function testMoveTo(): void
    {
        $file = new UploadedFile($this->stream, 12, UPLOAD_ERR_OK);
        $destination = tempnam(sys_get_temp_dir(), 'wfl_dest_test');

        $file->moveTo($destination);

        $this->assertFileExists($destination);
        $this->assertSame('Test content', file_get_contents($destination));

        // Test that stream is no longer available
        $this->expectException(RuntimeException::class);
        $file->getStream();

        unlink($destination);
    }

    public function testMoveToThrowsExceptionOnError(): void
    {
        $this->expectException(RuntimeException::class);
        $file = new UploadedFile($this->stream, 12, UPLOAD_ERR_FORM_SIZE); // Example error
        $file->moveTo(tempnam(sys_get_temp_dir(), 'wfl_dest_test'));
    }

    public function testMoveToThrowsExceptionIfAlreadyMoved(): void
    {
        $file = new UploadedFile($this->stream, 12, UPLOAD_ERR_OK);
        $destination = tempnam(sys_get_temp_dir(), 'wfl_dest_test');
        $file->moveTo($destination); // First move

        // Second move
        $this->expectException(RuntimeException::class);
        $file->moveTo(tempnam(sys_get_temp_dir(), 'wfl_dest_test_2'));

        unlink($destination);
    }
}
