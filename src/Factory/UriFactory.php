<?php

declare(strict_types=1);

namespace Waffle\Commons\Http\Factory;

use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Waffle\Commons\Http\Uri;

class UriFactory implements UriFactoryInterface
{
    #[\Override]
    public function createUri(string $uri = ''): UriInterface
    {
        return new Uri($uri);
    }
}
