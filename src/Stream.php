<?php

declare(strict_types=1);

namespace Waffle\Commons\Http;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * PSR-7 StreamInterface implementation.
 *
 * @see https://www.php-fig.org/psr/psr-7/#34-psrhttpmessagestreaminterface
 */
class Stream implements StreamInterface
{
    /**
     * @var resource|null A resource handle.
     */
    private $resource;

    /**
     * @var array|null Known stream metadata.
     */
    private ?array $meta = null;

    /**
     * @var array|null Detached resource metadata cache.
     */
    private ?array $detachedMeta = null;

    /**
     * Stream modes considered readable.
     */
    private const READABLE_MODES = '/r|a\+|ab\+|w\+|wb\+|x\+|xb\+|c\+|cb\+/';

    /**
     * Stream modes considered writable.
     */
    private const WRITABLE_MODES = '/a|w|r\+|rb\+|rw|x|c/';

    /**
     * @param resource|string $stream Stream resource or file path.
     * @param string $mode Mode with which to open stream (used if $stream is a path).
     * @throws InvalidArgumentException if $stream is not a resource or string.
     * @throws RuntimeException if $stream is a string and cannot be opened.
     */
    public function __construct($stream, string $mode = 'r')
    {
        if (is_string($stream)) {
            // If it's a string, assume it's a file path
            $resource = @fopen($stream, $mode);
            if (false === $resource) {
                throw new RuntimeException('Failed to open stream: ' . $stream);
            }
            $this->resource = $resource;
        } elseif (is_resource($stream)) {
            // If it's already a resource
            $this->resource = $stream;
        } else {
            // Invalid type
            throw new InvalidArgumentException('Invalid stream provided; must be a string (path) or resource.');
        }
    }

    /**
     * Destructor. Automatically closes the stream if it's still attached.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function __toString(): string
    {
        if (!$this->isReadable()) {
            return '';
        }

        try {
            $this->rewind();
            return $this->getContents();
        } catch (RuntimeException $e) {
            // Cannot throw exception from __toString
            return '';
        }
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function close(): void
    {
        if (null === $this->resource) {
            return;
        }

        $resource = $this->detach();
        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function detach()
    {
        if (null === $this->resource) {
            return null;
        }

        $resource = $this->resource;
        $this->resource = null;

        // Saves metadata in case it's needed after detachment
        if (null === $this->detachedMeta) {
            $this->detachedMeta = $this->meta;
        }
        $this->meta = null; // Clears metadata cache

        return $resource;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getSize(): ?int
    {
        if (null === $this->resource) {
            // If detached, return cached size if available
            return $this->detachedMeta['unread_bytes'] ?? null;
        }

        // Ensure file stats are up-to-date
        clearstatcache(true, $this->getMetadata('uri'));

        $stats = fstat($this->resource);
        return $stats['size'];
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function tell(): int
    {
        if (null === $this->resource) {
            throw new RuntimeException('Stream is detached.');
        }

        $result = ftell($this->resource);

        if (false === $result) {
            throw new RuntimeException('Unable to retrieve stream position.');
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function eof(): bool
    {
        return $this->resource ? feof($this->resource) : true;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function isSeekable(): bool
    {
        return $this->resource ? $this->getMetadata('seekable') ?? false : false;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->isSeekable()) {
            throw new RuntimeException('Stream is not seekable.');
        }

        if (0 !== fseek($this->resource, $offset, $whence)) {
            throw new RuntimeException(
                'Unable to seek to stream position ' . $offset . ' with whence ' . var_export($whence, true),
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function rewind(): void
    {
        $this->seek(0);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function isWritable(): bool
    {
        if (null === $this->resource) {
            return false;
        }
        $mode = $this->getMetadata('mode') ?? '';
        return (bool) preg_match(self::WRITABLE_MODES, $mode);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function write(string $string): int
    {
        if (!$this->isWritable()) {
            throw new RuntimeException('Stream is not writable.');
        }

        $result = fwrite($this->resource, $string);

        if (false === $result) {
            throw new RuntimeException('Unable to write to stream.');
        }

        // Invalidate metadata cache as size might have changed
        $this->meta = null;

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function isReadable(): bool
    {
        if (null === $this->resource) {
            return false;
        }
        $mode = $this->getMetadata('mode') ?? '';
        return (bool) preg_match(self::READABLE_MODES, $mode);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function read(int $length): string
    {
        if (!$this->isReadable()) {
            throw new RuntimeException('Stream is not readable.');
        }

        $result = fread($this->resource, $length);

        if (false === $result) {
            throw new RuntimeException('Unable to read from stream.');
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getContents(): string
    {
        if (!$this->isReadable()) {
            throw new RuntimeException('Stream is not readable.');
        }

        $result = stream_get_contents($this->resource);

        if (false === $result) {
            throw new RuntimeException('Unable to read stream contents.');
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getMetadata(?string $key = null)
    {
        if (null === $this->resource) {
            // Returns detached metadata if available
            return $this->detachedMeta[$key] ?? null;
        }

        // Fills cache if empty
        if (null === $this->meta) {
            $this->meta = stream_get_meta_data($this->resource);
        }

        // Returns all metadata
        if (null === $key) {
            return $this->meta;
        }

        // Returns a specific key
        return $this->meta[$key] ?? null;
    }
}
