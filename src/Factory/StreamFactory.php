<?php

declare(strict_types=1);

namespace Waffle\Commons\Http\Factory;

use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Waffle\Commons\Contracts\Data\Connection\ConnectionTrackerInterface;
use Waffle\Commons\Http\Stream;

class StreamFactory implements StreamFactoryInterface
{
    /**
     * @param ?ConnectionTrackerInterface $tracker DIAG-03 tracer threaded into every
     *        {@see Stream} this factory creates. Null (default) ⇒ tracing off.
     */
    public function __construct(
        private readonly ?ConnectionTrackerInterface $tracker = null,
    ) {}

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

        return new Stream($resource, tracker: $this->tracker);
    }

    #[\Override]
    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        return new Stream($filename, $mode, tracker: $this->tracker);
    }

    #[\Override]
    public function createStreamFromResource($resource): StreamInterface
    {
        return new Stream($resource, tracker: $this->tracker);
    }
}
