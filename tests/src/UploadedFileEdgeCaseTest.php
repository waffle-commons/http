<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypeError;
use Waffle\Commons\Http\UploadedFile;

/**
 * Targets edge cases for UploadedFile, specifically focusing on
 * file system errors and invalid states that are hard to reproduce naturally.
 */
#[CoversClass(UploadedFile::class)]
class UploadedFileEdgeCaseTest extends TestCase
{
    private string $tempFile;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->tempFile = tempnam(sys_get_temp_dir(), 'waffle_test_');
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            @unlink($this->tempFile);
        }
        parent::tearDown();
    }

    public function testMoveToThrowsExceptionOnWriteFailure(): void
    {
        // Pass the file path string, not a Stream object
        $uploadedFile = new UploadedFile($this->tempFile, 1024, UPLOAD_ERR_OK, 'test.txt', 'text/plain');

        // We try to move the file to a directory that definitely does not exist
        // and implies a permission error or "directory not found" error.
        $invalidPath = '/this/directory/does/not/exist/' . uniqid() . '/file.txt';

        $this->expectException(RuntimeException::class);

        // FIX: Use the error control operator '@' to suppress the native PHP warning
        // emitted by rename() failure. We only care about the Exception being thrown.
        @$uploadedFile->moveTo($invalidPath);
    }

    public function testMoveToThrowsExceptionIfStreamIsMoved(): void
    {
        $uploadedFile = new UploadedFile($this->tempFile, 1024, UPLOAD_ERR_OK);

        // First move (simulated success to a valid temp location)
        $target = $this->tempFile . '_moved';
        $uploadedFile->moveTo($target);

        // Clean up the moved file
        @unlink($target);

        // Second move should fail because the file is already marked as moved
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('moved');

        $uploadedFile->moveTo($target);
    }

    public function testConstructorThrowsExceptionOnInvalidType(): void
    {
        // Expect TypeError because constructor enforces string type hint
        $this->expectException(TypeError::class);

        new UploadedFile(['not a string'], 0, UPLOAD_ERR_OK); // Invalid type
    }

    public function testGetStreamThrowsExceptionIfMoved(): void
    {
        $uploadedFile = new UploadedFile($this->tempFile, 1024, UPLOAD_ERR_OK);

        $target = $this->tempFile . '_moved_stream';
        $uploadedFile->moveTo($target);
        @unlink($target);

        // PSR-7: getStream() must throw if the file has been moved
        $this->expectException(RuntimeException::class);
        $uploadedFile->getStream();
    }
}
