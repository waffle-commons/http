<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Waffle\Commons\Contracts\Data\Connection\ConnectionKind;
use Waffle\Commons\Contracts\Data\Connection\ConnectionTrackerInterface;
use Waffle\Commons\Http\Factory\StreamFactory;
use Waffle\Commons\Http\Stream;

#[CoversClass(Stream::class)]
#[CoversClass(StreamFactory::class)]
final class StreamTracerTest extends TestCase
{
    public function testAdoptedResourceIsTrackedOpen(): void
    {
        $tracker = $this->recordingTracker();
        $resource = $this->memoryResource();

        // Hold the reference: an unreferenced Stream is GC'd immediately, whose
        // __destruct closes it (the trackClose-on-GC path is asserted separately).
        $stream = new Stream($resource, tracker: $tracker);

        $open = $tracker->openConnections();
        self::assertCount(1, $open);
        self::assertSame(ConnectionKind::Stream, $open[0]['kind'] ?? null);
        self::assertTrue($stream->isReadable());
    }

    public function testPathOpenedStreamIsTrackedOpen(): void
    {
        $tracker = $this->recordingTracker();

        $stream = new Stream('php://memory', 'r+', tracker: $tracker);

        self::assertCount(1, $tracker->openConnections());
        self::assertTrue($stream->isReadable());
    }

    public function testUnreferencedStreamIsClosedOnGarbageCollection(): void
    {
        $tracker = $this->recordingTracker();

        // No reference kept ⇒ immediate destruction ⇒ close() ⇒ trackClose().
        new Stream($this->memoryResource(), tracker: $tracker);

        self::assertSame([], $tracker->openConnections());
    }

    public function testCloseTracksTheStreamClosed(): void
    {
        $tracker = $this->recordingTracker();
        $stream = new Stream($this->memoryResource(), tracker: $tracker);

        $stream->close();

        self::assertSame([], $tracker->openConnections());
    }

    public function testDetachTracksTheStreamClosed(): void
    {
        $tracker = $this->recordingTracker();
        $stream = new Stream($this->memoryResource(), tracker: $tracker);

        $stream->detach();

        self::assertSame([], $tracker->openConnections());
    }

    public function testWithoutTrackerConstructionIsANoOp(): void
    {
        $stream = new Stream($this->memoryResource());

        self::assertTrue($stream->isReadable());
    }

    public function testFactoryThreadsTrackerIntoCreatedStreams(): void
    {
        $tracker = $this->recordingTracker();
        $factory = new StreamFactory($tracker);

        $stream = $factory->createStream('payload');

        self::assertCount(1, $tracker->openConnections());
        self::assertSame('payload', (string) $stream);
    }

    public function testFactoryWithoutTrackerStillWorks(): void
    {
        $stream = new StreamFactory()->createStream('payload');

        self::assertSame('payload', (string) $stream);
    }

    /**
     * @return resource
     */
    private function memoryResource()
    {
        $resource = fopen('php://memory', 'r+');
        if ($resource === false) {
            throw new RuntimeException('Unable to open php://memory for the test.');
        }

        return $resource;
    }

    private function recordingTracker(): ConnectionTrackerInterface
    {
        return new class implements ConnectionTrackerInterface {
            /** @var array<string, ConnectionKind> */
            private array $open = [];

            #[\Override]
            public function trackOpen(string $id, ConnectionKind $kind): void
            {
                $this->open[$id] = $kind;
            }

            #[\Override]
            public function trackClose(string $id): void
            {
                unset($this->open[$id]);
            }

            #[\Override]
            public function openConnections(): array
            {
                $connections = [];
                foreach ($this->open as $id => $kind) {
                    $connections[] = ['id' => $id, 'kind' => $kind];
                }

                return $connections;
            }

            #[\Override]
            public function reset(): void
            {
                $this->open = [];
            }
        };
    }
}
