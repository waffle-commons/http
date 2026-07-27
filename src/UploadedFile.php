<?php

declare(strict_types=1);

namespace Waffle\Commons\Http;

use IgorPhp\IgorBundle\Attribute\WorkerSafe;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Waffle\Commons\Utils\Assert;

/**
 * PSR-7 UploadedFileInterface implementation.
 *
 * @see https://www.php-fig.org/psr/psr-7/#36-psrhttpmessageuploadedfileinterface
 */
final class UploadedFile implements UploadedFileInterface
{
    /** @var StreamInterface|null */
    #[WorkerSafe(reason: 'per-request value object; lazy stream is instance-scoped, never shared')]
    private ?StreamInterface $stream;
    /** @var string|null Filesystem path ($_FILES upload), or null when stream-backed. */
    private readonly ?string $file;
    /** @var bool Indicates if moveTo() has been called. */
    #[WorkerSafe(reason: 'per-request value object; one-shot moved-latch, never shared')]
    private bool $hasMoved = false;

    /**
     * @param string|StreamInterface $streamOrFile A filesystem path (e.g. a
     *        `$_FILES` upload) OR a stream holding the uploaded content. When a
     *        stream is given NO temporary file is created (STATE-02): the content
     *        lives in the stream and is copied straight to its destination on
     *        moveTo(), so the upload path never touches a shared temp directory.
     * @param int $size File size in bytes.
     * @param int $error PHP UPLOAD_ERR_* error code.
     * @param string|null $clientFilename Original filename on client side.
     * @param string|null $clientMediaType MIME type as sent by client.
     * @param string|null $baseDir SEC-03: an existing directory `moveTo()`
     *        destinations must resolve inside, enforced via
     *        {@see Assert::within()}. `Assert::safePath()` alone only rejects
     *        literal `..` traversal segments — it does not stop a fully
     *        qualified destination (e.g. attacker-influenced metadata used
     *        verbatim as the target) from pointing outside the directory the
     *        caller actually intends. Left `null` (the default) preserves prior
     *        behaviour for callers with no configured upload root; callers that
     *        DO have one should always supply it.
     */
    public function __construct(
        string|StreamInterface $streamOrFile,
        private int $size,
        private int $error,
        private ?string $clientFilename = null,
        private ?string $clientMediaType = null,
        private ?string $baseDir = null,
    ) {
        if (is_string($streamOrFile)) {
            $this->file = $streamOrFile;
            $this->stream = null;
        } else {
            $this->file = null;
            $this->stream = $streamOrFile;
        }
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
        if ($this->stream !== null) {
            return $this->stream;
        }
        // File-backed upload: open the temporary file lazily. $this->file is
        // non-null whenever no stream was supplied (see the constructor); the
        // `?? ''` keeps fopen() type-safe and falls through to the error below.
        $resource = fopen(filename: $this->file ?? '', mode: 'r');
        if (false === $resource) {
            throw new RuntimeException('Failed to open uploaded file for reading.');
        }
        $this->stream = new Stream($resource);
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

        // SEC-05: reject a directory-traversal or null-byte destination before
        // any transfer, so attacker-influenced metadata can never escape the
        // intended storage location. Throws a ValidationException (an
        // InvalidArgumentException, per the PSR-7 moveTo() contract).
        $targetPath = Assert::safePath($targetPath);

        // SEC-03: safePath() only screens the literal string for `..` segments;
        // it cannot tell a legitimate absolute destination from one that fully
        // replaces the intended location (e.g. unsanitized client-supplied
        // metadata used as-is). When a base directory is configured, additionally
        // require the resolved target to stay lexically inside it.
        if ($this->baseDir !== null) {
            $targetPath = Assert::within($this->baseDir, $targetPath);
        }

        if ($this->file !== null) {
            $this->moveBackingFile($this->file, $targetPath);
        } else {
            // STATE-02: stream-backed upload — copy the content straight to the
            // destination in bounded chunks; no shared temp directory involved.
            $this->copyStreamTo($targetPath);
        }

        $this->hasMoved = true;
    }

    /**
     * Moves a filesystem-backed upload, preferring move_uploaded_file() under a
     * web SAPI (more secure) and falling back to rename() on CLI.
     */
    private function moveBackingFile(string $source, string $targetPath): void
    {
        $isSapi = !in_array(needle: PHP_SAPI, haystack: ['cli', 'phpdbg'], strict: true);

        if ($isSapi && is_uploaded_file($source)) {
            if (!move_uploaded_file($source, $targetPath)) {
                throw new RuntimeException('Failed to move uploaded file.');
            }
            return;
        }

        if (!rename($source, $targetPath)) {
            throw new RuntimeException('Failed to move file.');
        }
    }

    /**
     * Streams a memory/temp-backed upload to its destination in 8 KiB chunks so
     * a large payload never inflates the per-worker memory ceiling.
     */
    private function copyStreamTo(string $targetPath): void
    {
        $source = $this->getStream();
        $target = fopen(filename: $targetPath, mode: 'w');
        if (false === $target) {
            throw new RuntimeException('Failed to open destination for writing.');
        }

        if ($source->isSeekable()) {
            $source->rewind();
        }

        try {
            while (!$source->eof()) {
                $chunk = $source->read(8192);
                if ($chunk === '') {
                    break;
                }
                // fwrite() can write fewer bytes than requested (e.g. a full disk
                // returns a short count, not false); loop until the whole chunk
                // lands or a write genuinely makes no progress.
                $offset = 0;
                $length = strlen($chunk);
                while ($offset < $length) {
                    $written = fwrite($target, substr($chunk, $offset));
                    if ($written === false || $written === 0) {
                        throw new RuntimeException('Failed to write uploaded content to destination.');
                    }
                    $offset += $written;
                }
            }
        } finally {
            fclose($target);
        }
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
