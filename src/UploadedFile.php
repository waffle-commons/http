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
    /** @var StreamInterface|null */
    private ?StreamInterface $stream = null;
    /** @var bool Indicates if moveTo() has been called. */
    private bool $hasMoved = false;

    /**
     * @param string $tmpName Path to the temporary file.
     * @param int $size File size in bytes.
     * @param int $error PHP UPLOAD_ERR_* error code.
     * @param string|null $clientFilename Original filename on client side.
     * @param string|null $clientMediaType MIME type as sent by client.
     */
    public function __construct(
        private string $tmpName,
        private int $size,
        private int $error,
        private ?string $clientFilename = null,
        private ?string $clientMediaType = null,
    ) {
        // No action needed here, properties are promoted.
    }

    /**
     * {@inheritdoc}
     */
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
            // Opens the temporary file in read mode
            $this->stream = new Stream(fopen($this->tmpName, 'r'));
        }
        return $this->stream;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function moveTo(string $targetPath): void
    {
        if ($this->error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Cannot move file due to upload error.');
        }
        if ($this->hasMoved) {
            throw new RuntimeException('Cannot move file; already moved.');
        }

        // Determines if environment is SAPI (e.g., FPM, Apache) or not (CLI)
        $isSapi = !in_array(PHP_SAPI, ['cli', 'phpdbg'], true);

        if ($isSapi && is_uploaded_file($this->tmpName)) {
            // Use move_uploaded_file for SAPI environments (more secure)
            if (!move_uploaded_file($this->tmpName, $targetPath)) {
                throw new RuntimeException('Failed to move uploaded file.');
            }
        } else {
            // Use rename for non-SAPI environments (e.g., CLI tests)
            if (!rename($this->tmpName, $targetPath)) {
                throw new RuntimeException('Failed to move file.');
            }
        }

        $this->hasMoved = true;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }
}
