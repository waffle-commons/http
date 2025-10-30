<?php

declare(strict_types=1);

namespace Waffle\Commons\Http {
    use WaffleTests\Commons\Http\UploadedFileTest;

    function rename(string $from, string $to, $context = null): bool
    {
        if (UploadedFileTest::$mockRenameReturnFalse) {
            return false;
        }
        return \rename($from, $to, $context);
    }
}

namespace WaffleTests\Commons\Http {

    use RuntimeException;
    use Waffle\Commons\Http\Stream;
    use Waffle\Commons\Http\UploadedFile;

    class UploadedFileTest extends AbstractTestCase
    {
        public static bool $mockRenameReturnFalse = false;

        private string $tempFile;

        protected function setUp(): void
        {
            // Réinitialise le mock avant chaque test
            self::$mockRenameReturnFalse = false;

            $this->tempFile = tempnam(sys_get_temp_dir(), 'wfl_upload_test');
            if ($this->tempFile === false) {
                $this->fail('Unable to create temporary file');
            }
            file_put_contents($this->tempFile, 'Test content');
        }

        protected function tearDown(): void
        {
            // Nettoyage du flag
            self::$mockRenameReturnFalse = false;

            if (file_exists($this->tempFile)) {
                unlink($this->tempFile);
            }
        }

        public function testConstructor(): void
        {
            $file = new UploadedFile($this->tempFile, 12, UPLOAD_ERR_OK, 'test.txt', 'text/plain');

            $this->assertSame(12, $file->getSize());
            $this->assertSame(UPLOAD_ERR_OK, $file->getError());
            $this->assertSame('test.txt', $file->getClientFilename());
            $this->assertSame('text/plain', $file->getClientMediaType());
        }

        public function testConstructorIgnoresStreamIfError(): void
        {
            $file = new UploadedFile('', 0, UPLOAD_ERR_NO_FILE);

            $this->expectException(RuntimeException::class);
            $file->getStream();
        }

        public function testGetStream(): void
        {
            $file = new UploadedFile($this->tempFile, 12, UPLOAD_ERR_OK);
            $stream = $file->getStream();

            $this->assertInstanceOf(Stream::class, $stream);
            $this->assertSame('Test content', $stream->getContents());
            $stream->close();
        }

        public function testGetStreamThrowsExceptionAfterMove(): void
        {
            $file = new UploadedFile($this->tempFile, 12, UPLOAD_ERR_OK);
            $dest = tempnam(sys_get_temp_dir(), 'wfl_dest');

            $file->moveTo($dest);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Cannot retrieve stream after file has been moved');

            try {
                $file->getStream();
            } finally {
                unlink($dest);
            }
        }

        public function testMoveTo(): void
        {
            $file = new UploadedFile($this->tempFile, 12, UPLOAD_ERR_OK);
            $destination = tempnam(sys_get_temp_dir(), 'wfl_dest_test');

            $file->moveTo($destination);

            $this->assertFileExists($destination);
            $this->assertSame('Test content', file_get_contents($destination));
            $this->assertFileDoesNotExist($this->tempFile);

            unlink($destination);
        }

        public function testMoveToThrowsExceptionOnError(): void
        {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Cannot move file due to upload error.');
            $file = new UploadedFile($this->tempFile, 12, UPLOAD_ERR_FORM_SIZE);
            $file->moveTo(tempnam(sys_get_temp_dir(), 'wfl_dest_test'));
        }

        public function testMoveToThrowsExceptionIfAlreadyMoved(): void
        {
            $file = new UploadedFile($this->tempFile, 12, UPLOAD_ERR_OK);
            $destination = tempnam(sys_get_temp_dir(), 'wfl_dest_test');
            $file->moveTo($destination);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Cannot move file; already moved.');
            $file->moveTo(tempnam(sys_get_temp_dir(), 'wfl_dest_test_2'));

            unlink($destination);
        }

        public function testMoveToThrowsExceptionOnRenameFailure(): void
        {
            $destination = '/any/path/does/not/matter/because/mocked';

            self::$mockRenameReturnFalse = true;

            $file = new UploadedFile($this->tempFile, 12, UPLOAD_ERR_OK);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Failed to move file');

            $file->moveTo($destination);
        }
    }
}
