<?php

declare(strict_types=1);

namespace Waffle\Commons\Http\Factory;

use Psr\Http\Message\ServerRequestInterface;
use Waffle\Commons\Contracts\Http\ServerRequestFactoryInterface;
use Waffle\Commons\Http\ServerRequest;
use Waffle\Commons\Http\Uri;

class ServerRequestFactory implements ServerRequestFactoryInterface
{
    #[\Override]
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        if (is_string($uri)) {
            $uri = new Uri($uri);
        }

        return new ServerRequest($method, $uri, [], null, '1.1', $serverParams);
    }
}
