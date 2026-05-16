<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Http;

use PHPUnit\Framework\TestCase;
use Waffle\Commons\Http\Stream;

/**
 * Abstract base class for all test cases in the Http component.
 *
 * This can be used to add shared helper methods or setup/teardown
 * logic common to all tests in this component.
 */
abstract class AbstractTestCase extends TestCase
{
    /**
     * Helper to create a new Stream with the given content.
     *
     * @param string $content
     * @return Stream
     */
    protected function createStream(string $content = ''): Stream
    {
        $resource = fopen(filename: 'php://temp', mode: 'r+');
        if (false === $resource) {
            $this->fail('Failed to open php://temp stream for testing.');
        }

        $stream = new Stream($resource);
        if ($content !== '') {
            $stream->write($content);
            $stream->rewind();
        }
        return $stream;
    }
}
