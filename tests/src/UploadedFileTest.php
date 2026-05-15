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

        #[\Override]
        protected function setUp(): void
        {
            // Réinitialise le mock avant chaque test
            self::$mockRenameReturnFalse = false;

            $tempFile = tempnam(directory: sys_get_temp_dir(), prefix: 'wfl_upload_test');
            $this->assertIsString($tempFile);
            $this->tempFile = $tempFile;
            file_put_contents(filename: $this->tempFile, data: 'Test content');
        }

        #[\Override]
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

            static::assertSame(12, $file->getSize());
            static::assertSame(UPLOAD_ERR_OK, $file->getError());
            static::assertSame('test.txt', $file->getClientFilename());
            static::assertSame('text/plain', $file->getClientMediaType());
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

            static::assertInstanceOf(Stream::class, $stream);
            static::assertSame('Test content', $stream->getContents());
            $stream->close();
        }

        public function testGetStreamThrowsExceptionAfterMove(): void
        {
            $file = new UploadedFile($this->tempFile, 12, UPLOAD_ERR_OK);
            $dest = tempnam(directory: sys_get_temp_dir(), prefix: 'wfl_dest');
            static::assertIsString($dest);

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
            $destination = tempnam(directory: sys_get_temp_dir(), prefix: 'wfl_dest_test');
            static::assertIsString($destination);

            $file->moveTo($destination);

            static::assertFileExists($destination);
            static::assertSame('Test content', file_get_contents($destination));
            static::assertFileDoesNotExist($this->tempFile);

            unlink($destination);
        }

        public function testMoveToThrowsExceptionOnError(): void
        {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Cannot move file due to upload error.');
            $file = new UploadedFile($this->tempFile, 12, UPLOAD_ERR_FORM_SIZE);
            $dest = tempnam(directory: sys_get_temp_dir(), prefix: 'wfl_dest_test');
            static::assertIsString($dest);
            $file->moveTo($dest);
        }

        public function testMoveToThrowsExceptionIfAlreadyMoved(): void
        {
            $file = new UploadedFile($this->tempFile, 12, UPLOAD_ERR_OK);
            $destination = tempnam(directory: sys_get_temp_dir(), prefix: 'wfl_dest_test');
            static::assertIsString($destination);
            $file->moveTo($destination);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Cannot move file; already moved.');
            $dest2 = tempnam(directory: sys_get_temp_dir(), prefix: 'wfl_dest_test_2');
            static::assertIsString($dest2);
            $file->moveTo($dest2);

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
