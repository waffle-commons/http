<?php

declare(strict_types=1);

namespace Waffle\Commons\Http\Factory;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Waffle\Commons\Http\Request;
use Waffle\Commons\Http\Uri;

class RequestFactory implements RequestFactoryInterface
{
    #[\Override]
    public function createRequest(string $method, $uri): RequestInterface
    {
        if (is_string($uri)) {
            $uri = new Uri($uri);
        }

        return new Request($method, $uri);
    }
}
