<?php

declare(strict_types=1);

namespace Waffle\Commons\Http\Factory;

use PHPUnit\Framework\TestCase;
use RuntimeException;

// Mock fopen in the same namespace as the class under test
function fopen(string $filename, string $mode)
{
    if ($filename === 'php://temp' && $mode === 'r+' && StreamFactoryTest::$mockFopenFail) {
        return false;
    }
    return \fopen($filename, $mode);
}

class StreamFactoryTest extends TestCase
{
    public static bool $mockFopenFail = false;

    #[\Override]
    protected function tearDown(): void
    {
        self::$mockFopenFail = false;
    }

    public function testCreateStreamThrowsExceptionOnFopenFailure(): void
    {
        self::$mockFopenFail = true;

        $factory = new StreamFactory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to open php://temp stream.');

        $factory->createStream('content');
    }

    public function testCreateStreamSuccess(): void
    {
        $factory = new StreamFactory();
        $stream = $factory->createStream('test');

        static::assertSame('test', (string) $stream);
    }

    public function testCreateStreamFromFile(): void
    {
        $factory = new StreamFactory();
        $tmp = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmp, 'file content');

        $stream = $factory->createStreamFromFile($tmp);
        static::assertSame('file content', (string) $stream);

        unlink($tmp);
    }

    public function testCreateStreamFromResource(): void
    {
        $factory = new StreamFactory();
        $resource = \fopen('php://memory', 'r+');
        fwrite($resource, 'resource content');

        $stream = $factory->createStreamFromResource($resource);
        static::assertSame('resource content', (string) $stream);
    }
}
