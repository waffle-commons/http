<?php

declare(strict_types=1);

namespace Waffle\Commons\Http;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * PSR-7 StreamInterface implementation.
 *
 * This class is a wrapper around a PHP stream resource.
 *
 * @see https://www.php-fig.org/psr/psr-7/#34-psrhttpmessagestreaminterface
 */
class Stream implements StreamInterface
{
    /** @var resource|null */
    private $resource;

    /**
     * @param resource $resource
     */
    public function __construct($resource)
    {
        if (!is_resource($resource) || get_resource_type($resource) !== 'stream') {
            throw new \InvalidArgumentException('Invalid stream resource provided.');
        }
        $this->resource = $resource;
    }

    #[\Override]
    public function __toString(): string
    {
        try {
            $this->rewind();
            return $this->getContents();
        } catch (RuntimeException $e) {
            return '';
        }
    }

    #[\Override]
    public function close(): void
    {
        if ($this->resource) {
            fclose($this->resource);
            $this->detach();
        }
    }

    #[\Override]
    public function detach()
    {
        if (!$this->resource) {
            return null;
        }
        $resource = $this->resource;
        $this->resource = null;
        return $resource;
    }

    #[\Override]
    public function getSize(): null|int
    {
        if (!$this->resource) {
            return null;
        }
        $stats = fstat($this->resource);
        return $stats['size'] ?? null;
    }

    #[\Override]
    public function tell(): int
    {
        if (!$this->resource || ($pos = ftell($this->resource)) === false) {
            throw new RuntimeException('Could not determine stream position.');
        }
        return $pos;
    }

    #[\Override]
    public function eof(): bool
    {
        return !$this->resource || feof($this->resource);
    }

    #[\Override]
    public function isSeekable(): bool
    {
        return $this->resource && $this->getMetadata('seekable');
    }

    #[\Override]
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->isSeekable() || fseek($this->resource, $offset, $whence) === -1) {
            throw new RuntimeException('Could not seek in stream.');
        }
    }

    #[\Override]
    public function rewind(): void
    {
        $this->seek(0);
    }

    #[\Override]
    public function isWritable(): bool
    {
        return $this->resource && $this->isModeWritable($this->getMetadata('mode'));
    }

    private function isModeWritable(string $mode): bool
    {
        return (
            str_contains($mode, 'w')
            || str_contains($mode, 'a')
            || str_contains($mode, 'x')
            || str_contains($mode, 'c')
            || str_contains($mode, '+')
        );
    }

    #[\Override]
    public function write(string $string): int
    {
        if (!$this->isWritable() || ($bytes = fwrite($this->resource, $string)) === false) {
            throw new RuntimeException('Could not write to stream.');
        }
        return $bytes;
    }

    #[\Override]
    public function isReadable(): bool
    {
        return (
            $this->resource
            && (str_contains($this->getMetadata('mode'), 'r') || str_contains($this->getMetadata('mode'), '+'))
        );
    }

    #[\Override]
    public function read(int $length): string
    {
        if (!$this->isReadable() || ($data = fread($this->resource, $length)) === false) {
            throw new RuntimeException('Could not read from stream.');
        }
        return $data;
    }

    #[\Override]
    public function getContents(): string
    {
        if (!$this->isReadable() || ($contents = stream_get_contents($this->resource)) === false) {
            throw new RuntimeException('Could not get stream contents.');
        }
        return $contents;
    }

    #[\Override]
    public function getMetadata(null|string $key = null)
    {
        if (!$this->resource) {
            return $key ? null : [];
        }
        $meta = stream_get_meta_data($this->resource);
        return $key === null ? $meta : $meta[$key] ?? null;
    }
}
