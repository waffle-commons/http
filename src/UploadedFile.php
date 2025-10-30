<?php

declare(strict_types=1);

namespace Waffle\Commons\Http;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/**
 * PSR-7 UploadedFileInterface implementation.
 *
 * @see https://www.php-fig.org/psr/psr-7/#36-psrhttpmessageuploadedfileinterface
 */
class UploadedFile implements UploadedFileInterface
{
    private null|StreamInterface $stream = null;
    private bool $hasMoved = false;

    public function __construct(
        private string $tmpName,
        private int $size,
        private int $error,
        private null|string $clientFilename = null,
        private null|string $clientMediaType = null,
    ) {
        if ($this->error !== UPLOAD_ERR_OK) {
            // If there's an error, no stream is available.
            return;
        }
    }

    #[\Override]
    public function getStream(): StreamInterface
    {
        if ($this->error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Cannot retrieve stream due to upload error.');
        }
        if ($this->hasMoved) {
            throw new RuntimeException('Cannot retrieve stream after file has been moved.');
        }
        if ($this->stream === null) {
            $this->stream = new Stream(fopen($this->tmpName, 'r'));
        }
        return $this->stream;
    }

    #[\Override]
    public function moveTo(string $targetPath): void
    {
        if ($this->error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Cannot move file due to upload error.');
        }
        if ($this->hasMoved) {
            throw new RuntimeException('Cannot move file; already moved.');
        }

        $isSapi = !in_array(PHP_SAPI, ['cli', 'phpdbg'], true);
        if ($isSapi && is_uploaded_file($this->tmpName)) {
            if (!move_uploaded_file($this->tmpName, $targetPath)) {
                throw new RuntimeException('Failed to move uploaded file.');
            }
        } else {
            // Not SAPI or not an uploaded file (e.g., in tests)
            if (!rename($this->tmpName, $targetPath)) {
                throw new RuntimeException('Failed to move file.');
            }
        }

        $this->hasMoved = true;
    }

    #[\Override]
    public function getSize(): int
    {
        return $this->size;
    }

    #[\Override]
    public function getError(): int
    {
        return $this->error;
    }

    #[\Override]
    public function getClientFilename(): null|string
    {
        return $this->clientFilename;
    }

    #[\Override]
    public function getClientMediaType(): null|string
    {
        return $this->clientMediaType;
    }
}
