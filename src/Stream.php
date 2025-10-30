<?php

declare(strict_types=1);

namespace Waffle\Commons\Http;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

class Stream implements StreamInterface
{
    /**
     * @var resource|null A resource handle.
     */
    private $resource;

    /**
     * @var array|null Known stream metadata.
     */
    private null|array $meta = null;

    /**
     * Detached resource metadata cache.
     *
     * @var array|null
     */
    private null|array $detachedMeta = null;

    private const READABLE_MODES = '/r|a\+|ab\+|w\+|wb\+|x\+|xb\+|c\+|cb\+/';
    private const WRITABLE_MODES = '/a|w|r\+|rb\+|rw|x|c/';

    /**
     * @param resource|string $stream Stream resource or file path.
     * @param string $mode Mode with which to open stream
     * @throws InvalidArgumentException if $stream is not a resource or string.
     * @throws RuntimeException if $stream is a string and cannot be opened.
     */
    public function __construct($stream, string $mode = 'r')
    {
        if (is_string($stream)) {
            $resource = @fopen($stream, $mode);
            if (false === $resource) {
                throw new RuntimeException('Failed to open stream: ' . $stream);
            }
            $this->resource = $resource;
        } elseif (is_resource($stream)) {
            $this->resource = $stream;
        } else {
            throw new InvalidArgumentException('Invalid stream resource provided; must be a string or resource.');
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    public function __toString(): string
    {
        if (!$this->isReadable()) {
            return '';
        }

        try {
            $this->rewind();
            return $this->getContents();
        } catch (RuntimeException $e) {
            return '';
        }
    }

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

    public function detach()
    {
        if (null === $this->resource) {
            return null;
        }

        $resource = $this->resource;
        $this->resource = null;

        if (null === $this->detachedMeta) {
            $this->detachedMeta = $this->meta;
        }
        $this->meta = null;

        return $resource;
    }

    public function getSize(): null|int
    {
        if (null === $this->resource) {
            return $this->detachedMeta['unread_bytes'] ?? null;
        }

        // Clear stat cache
        clearstatcache(true, $this->getMetadata('uri'));

        $stats = fstat($this->resource);
        return $stats['size'] ?? null;
    }

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

    public function eof(): bool
    {
        return $this->resource ? feof($this->resource) : true;
    }

    public function isSeekable(): bool
    {
        return $this->resource ? $this->getMetadata('seekable') ?? false : false;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->isSeekable()) {
            throw new RuntimeException('Stream is not seekable.');
        }

        if (0 !== fseek($this->resource, $offset, $whence)) {
            throw new RuntimeException('Unable to seek to stream position '
            . $offset
            . ' with whence '
            . var_export($whence, true));
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        if (null === $this->resource) {
            return false;
        }
        $mode = $this->getMetadata('mode') ?? '';
        return (bool) preg_match(self::WRITABLE_MODES, $mode);
    }

    public function write(string $string): int
    {
        if (!$this->isWritable()) {
            throw new RuntimeException('Stream is not writable.');
        }

        $result = fwrite($this->resource, $string);

        if (false === $result) {
            throw new RuntimeException('Unable to write to stream.');
        }

        // Invalidate metadata cache
        $this->meta = null;

        return $result;
    }

    public function isReadable(): bool
    {
        if (null === $this->resource) {
            return false;
        }
        $mode = $this->getMetadata('mode') ?? '';
        return (bool) preg_match(self::READABLE_MODES, $mode);
    }

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

    public function getMetadata(null|string $key = null)
    {
        if (null === $this->resource) {
            return $this->detachedMeta[$key] ?? null;
        }

        if (null === $this->meta) {
            $this->meta = stream_get_meta_data($this->resource);
        }

        if (null === $key) {
            return $this->meta;
        }

        return $this->meta[$key] ?? null;
    }
}
