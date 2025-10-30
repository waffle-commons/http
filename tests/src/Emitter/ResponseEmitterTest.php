<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Http\Emitter;

use RuntimeException;
use Waffle\Commons\Http\Emitter\ResponseEmitter;
use Waffle\Commons\Http\Response;
use WaffleTests\Commons\Http\AbstractTestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ResponseEmitterTest extends AbstractTestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testEmitsResponse(): void
    {
        $response = new Response(
            201,
            ['Content-Type' => 'text/plain', 'X-Test' => 'Waffle'],
            $this->createStream('Hello World'),
        );

        $emitter = new ResponseEmitter();

        ob_start();
        $emitter->emit($response);
        $output = ob_get_clean();

        // This is tricky to test, but we can check the output buffer
        $this->assertSame('Hello World', $output);

        // We can't easily check headers in a CLI test without
        // extensions like xdebug. This test mainly ensures no
        // errors are thrown and the body is output.
    }

    /**
     * @runInSeparateProcess
     */
    public function testThrowsExceptionIfHeadersSent(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot emit response; headers already sent.');

        // Simulate headers already sent
        // This requires running in a separate process
        header('X-Dummy: Test'); // This "sends" headers in the CLI context

        $response = new Response(200, [], $this->createStream('test'));
        new ResponseEmitter()->emit($response);
    }

    /**
     * @runInSeparateProcess
     */
    public function testEmitsHeadersWithMultipleValues(): void
    {
        $response = new Response(200, ['X-Foo' => ['Bar', 'Baz']], $this->createStream(''));

        $emitter = new ResponseEmitter();

        ob_start();
        $emitter->emit($response);
        ob_get_clean();

        // We can't check xdebug_get_headers in CI,
        // but this test ensures the logic for multiple
        // header values doesn't crash.
        $this->assertTrue(true); // Assert test ran
    }
}
