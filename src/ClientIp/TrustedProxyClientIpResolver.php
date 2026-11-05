<?php

declare(strict_types=1);

namespace Waffle\Commons\Http\ClientIp;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Waffle\Commons\Contracts\Http\ClientIpResolverInterface;

/**
 * The client address behind trusted proxies ([NET-01]).
 *
 * `X-Forwarded-For` is read only when `REMOTE_ADDR` is an allow-listed proxy, and only from the right: each
 * hop a trusted proxy appended is skipped, and the first address that is not a trusted proxy is the
 * client. A client can prepend anything it likes to the header; it cannot make a trusted proxy append a
 * false hop. A malformed hop stops the walk, and `REMOTE_ADDR` is returned rather than a guess.
 */
final readonly class TrustedProxyClientIpResolver implements ClientIpResolverInterface
{
    /** @var list<array{string, int}> Packed network address and prefix length. */
    private array $networks;

    /**
     * @param list<string> $trustedProxies Proxy addresses or CIDR ranges (`10.0.0.0/8`, `fd00::/8`).
     *
     * @throws InvalidArgumentException When an entry is neither an IP address nor a CIDR range.
     */
    public function __construct(array $trustedProxies = [])
    {
        $networks = [];
        foreach ($trustedProxies as $entry) {
            $networks[] = self::parse($entry);
        }

        $this->networks = $networks;
    }

    #[\Override]
    public function resolve(ServerRequestInterface $request): ?string
    {
        $params = $request->getServerParams();
        if (!is_string($params['REMOTE_ADDR'] ?? null)) {
            return null;
        }

        $remote = $params['REMOTE_ADDR'];
        if (inet_pton($remote) === false) {
            return null;
        }

        if (!$this->trusted($remote)) {
            return $remote;
        }

        $hops = array_map(trim(...), explode(',', $request->getHeaderLine('X-Forwarded-For')));
        $client = $remote;
        foreach (array_reverse($hops) as $hop) {
            if ($hop === '') {
                continue;
            }

            if (inet_pton($hop) === false) {
                return $remote;
            }

            $client = $hop;
            if (!$this->trusted($hop)) {
                return $hop;
            }
        }

        return $client;
    }

    private function trusted(string $address): bool
    {
        $packed = inet_pton($address);
        if ($packed === false) {
            return false;
        }

        foreach ($this->networks as [$network, $prefix]) {
            if (strlen($network) === strlen($packed) && self::inPrefix($packed, $network, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private static function inPrefix(string $address, string $network, int $prefix): bool
    {
        $bytes = intdiv($prefix, 8);
        if (substr($address, 0, $bytes) !== substr($network, 0, $bytes)) {
            return false;
        }

        $bits = $prefix % 8;
        if ($bits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $bits)) & 0xFF;

        return (ord($address[$bytes]) & $mask) === (ord($network[$bytes]) & $mask);
    }

    /**
     * @return array{string, int}
     *
     * @throws InvalidArgumentException
     */
    private static function parse(string $entry): array
    {
        $parts = explode('/', $entry, 2);
        $packed = inet_pton($parts[0]);
        if ($packed === false) {
            throw new InvalidArgumentException('Not an IP address or CIDR range: "' . $entry . '".');
        }

        $max = strlen($packed) * 8;
        $prefixText = $parts[1] ?? null;
        if ($prefixText === null) {
            return [$packed, $max];
        }

        $prefix = filter_var($prefixText, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => $max]]);
        if ($prefix === false) {
            throw new InvalidArgumentException('Invalid CIDR prefix in "' . $entry . '".');
        }

        return [$packed, $prefix];
    }
}
