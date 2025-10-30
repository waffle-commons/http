<?php

declare(strict_types=1);

namespace Waffle\Commons\Http\Emitter;

use Psr\Http\Message\ResponseInterface;

/**
 * Emits a PSR-7 Response to the client.
 *
 * This class is responsible for the "dirty" work of sending
 * the status line, headers, and body using native PHP functions.
 */
class ResponseEmitter
{
    /**
     * Emits the given PSR-7 response.
     *
     * @param ResponseInterface $response The response to emit.
     * @throws \RuntimeException If headers have already been sent.
     */
    public function emit(ResponseInterface $response): void
    {
        if (headers_sent()) {
            throw new \RuntimeException('Cannot emit response; headers already sent.');
        }

        // 1. Send Status Line
        $statusCode = $response->getStatusCode();
        $reasonPhrase = $response->getReasonPhrase();
        header(
            sprintf(
                'HTTP/%s %d%s',
                $response->getProtocolVersion(),
                $statusCode,
                $reasonPhrase ? ' ' . $reasonPhrase : '', // Don't add space if phrase is empty
            ),
            true, // Replace any existing status line
            $statusCode, // Force the HTTP response code
        );

        // 2. Send Headers
        $this->emitHeaders($response->getHeaders());

        // 3. Send Body
        $this->emitBody($response);
    }

    /**
     * Emits all headers.
     *
     * @param array<string, string[]> $headers
     */
    private function emitHeaders(array $headers): void
    {
        foreach ($headers as $name => $values) {
            // Normalizes header name for display (e.g., 'content-type' -> 'Content-Type')
            $name = str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
            $first = true; // For replacement vs. add logic

            // Special case: Set-Cookie cannot be combined into one line
            if (strcasecmp($name, 'Set-Cookie') === 0) {
                foreach ($values as $value) {
                    header(sprintf('%s: %s', $name, $value), false); // 'false' to add, not replace
                }
                continue; // Continue to next header
            }

            // Combine other headers
            foreach ($values as $value) {
                header(
                    sprintf('%s: %s', $name, $value),
                    $first // Replaces header on first value, adds for subsequent ones
                );
                $first = false;
            }
        }
    }

    /**
     * Emits the response body.
     */
    private function emitBody(ResponseInterface $response): void
    {
        $body = $response->getBody();

        // If the body is seekable, ensure it's at the beginning
        if ($body->isSeekable()) {
            $body->rewind();
        }

        // Reads the stream in chunks to handle large files
        while (!$body->eof()) {
            echo $body->read(8192); // Read in 8KB chunks
        }
    }
}
