<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Http\Emitter;

/**
 * Holds static state for namespace-level PHP function mocks used in ResponseEmitterTest.
 */
final class MockState
{
    public static bool $headersSent = false;
}
