<?php

declare(strict_types=1);

// Define mocks in the target namespace to override built-in functions
namespace Waffle\Commons\Http\Emitter {
    /**
     * Mock for headers_sent().
     */
    function headers_sent(): bool
    {
        global $mockHeadersSent;
        return $mockHeadersSent ?? false;
    }

    /**
     * Mock for header().
     * Prevents actual output and PHPUnit errors about headers being sent.
     */
    function header(string $_header, bool $_replace = true, int $_http_response_code = 0): void
    {
        // no-op for tests
    }
}

namespace WaffleTests\Commons\Http\Emitter {
    use RuntimeException;
    use Waffle\Commons\Http\Emitter\ResponseEmitter;
    use Waffle\Commons\Http\Response;
    use WaffleTests\Commons\Http\AbstractTestCase;

    /**
     * These tests verify ResponseEmitter behavior.
     * The built-in functions `headers_sent` and `header` are mocked
     * in the namespace above.
     *
     * @preserveGlobalState disabled
     */
    class ResponseEmitterTest extends AbstractTestCase
    {
        #[\Override]
        protected function tearDown(): void
        {
            // Reset global mock state
            global $mockHeadersSent;
            $mockHeadersSent = false;
            parent::tearDown();
        }

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

            // We verify the response body
            static::assertSame('Hello World', $output);
        }

        public function testThrowsExceptionIfHeadersSent(): void
        {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Cannot emit response; headers already sent.');

            // Set the global flag to true, which our namespaced mock will read
            global $mockHeadersSent;
            $mockHeadersSent = true;

            $response = new Response(200, [], $this->createStream('test'));
            new ResponseEmitter()->emit($response);
        }

        public function testEmitsHeadersWithMultipleValues(): void
        {
            $response = new Response(200, ['X-Foo' => ['Bar', 'Baz']], $this->createStream(''));

            $emitter = new ResponseEmitter();

            ob_start();
            $emitter->emit($response);
            ob_get_clean();

            // Assertion: Just ensuring no crash occurs during header emission logic
            static::assertTrue(true);
        }
    }
}
