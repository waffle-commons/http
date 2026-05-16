<?php

declare(strict_types=1);

namespace Waffle\Commons\Http\Factory;

use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Waffle\Commons\Http\Stream;

class StreamFactory implements StreamFactoryInterface
{
    #[\Override]
    public function createStream(string $content = ''): StreamInterface
    {
        $resource = fopen(filename: 'php://temp', mode: 'r+');
        if (false === $resource) {
            throw new RuntimeException('Unable to open php://temp stream.');
        }
        assert(is_resource($resource), description: 'fopen php://temp must return a resource after false-check guard.');
        fwrite(stream: $resource, data: $content);
        fseek(stream: $resource, offset: 0);

        return new Stream($resource);
    }

    #[\Override]
    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        return new Stream($filename, $mode);
    }

    #[\Override]
    public function createStreamFromResource($resource): StreamInterface
    {
        return new Stream($resource);
    }
}
