<?php

declare(strict_types=1);

namespace Waffle\Commons\Http\Factory;

use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Waffle\Commons\Http\Stream;

class StreamFactory implements StreamFactoryInterface
{
    public function createStream(string $content = ''): StreamInterface
    {
        $resource = fopen('php://temp', 'r+');
        if (false === $resource) {
            throw new RuntimeException('Unable to open php://temp stream.');
        }
        fwrite($resource, $content);
        fseek($resource, 0);

        return new Stream($resource);
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        return new Stream($filename, $mode);
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        return new Stream($resource);
    }
}
