<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Http\ClientIp;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Waffle\Commons\Http\ClientIp\TrustedProxyClientIpResolver;
use Waffle\Commons\Http\Factory\ServerRequestFactory;
use WaffleTests\Commons\Http\AbstractTestCase;

#[CoversClass(TrustedProxyClientIpResolver::class)]
final class TrustedProxyClientIpResolverTest extends AbstractTestCase
{
    /**
     * @return iterable<string, array{list<string>, string|null, string, string|null}>
     */
    public static function cases(): iterable
    {
        yield 'no proxy configured: the forwarded header is ignored' => [
            [],
            '203.0.113.7',
            '198.51.100.1',
            '203.0.113.7',
        ];
        yield 'an untrusted peer: its header is ignored' => [
            ['10.0.0.0/8'],
            '203.0.113.7',
            '198.51.100.1',
            '203.0.113.7',
        ];
        yield 'one trusted proxy' => [['10.0.0.0/8'], '10.1.2.3', '198.51.100.1', '198.51.100.1'];
        yield 'a client-prepended hop cannot win' => [
            ['10.0.0.0/8'],
            '10.1.2.3',
            '6.6.6.6, 198.51.100.1',
            '198.51.100.1',
        ];
        yield 'two trusted proxies' => [
            ['10.0.0.0/8', '192.168.1.5'],
            '10.1.2.3',
            '198.51.100.1, 192.168.1.5',
            '198.51.100.1',
        ];
        yield 'every hop trusted: the left-most one' => [['10.0.0.0/8'], '10.1.2.3', '10.9.9.9, 10.8.8.8', '10.9.9.9'];
        yield 'no forwarded header' => [['10.0.0.0/8'], '10.1.2.3', '', '10.1.2.3'];
        yield 'a malformed hop falls back to the peer' => [['10.0.0.0/8'], '10.1.2.3', 'not-an-ip', '10.1.2.3'];
        yield 'IPv6 range' => [['fd00::/8'], 'fd12::1', '2001:db8::7', '2001:db8::7'];
        yield 'a /20 prefix that does not match' => [['10.0.16.0/20'], '10.0.32.1', '198.51.100.1', '10.0.32.1'];
        yield 'a /20 prefix that matches' => [['10.0.16.0/20'], '10.0.31.254', '198.51.100.1', '198.51.100.1'];
        yield 'an IPv4 proxy never matches an IPv6 peer' => [
            ['0.0.0.0/0'],
            '2001:db8::1',
            '198.51.100.1',
            '2001:db8::1',
        ];
        yield 'no peer address' => [['10.0.0.0/8'], null, '198.51.100.1', null];
    }

    /**
     * @param list<string> $proxies
     */
    #[DataProvider('cases')]
    public function testResolvesTheClientAddress(
        array $proxies,
        ?string $remote,
        string $forwarded,
        ?string $expected,
    ): void {
        $request = new ServerRequestFactory()->createServerRequest(
            'GET',
            'http://localhost/',
            $remote === null ? [] : ['REMOTE_ADDR' => $remote],
        );
        if ($forwarded !== '') {
            $request = $request->withHeader('X-Forwarded-For', $forwarded);
        }

        static::assertSame($expected, new TrustedProxyClientIpResolver($proxies)->resolve($request));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidEntries(): iterable
    {
        yield 'not an address' => ['proxy.internal'];
        yield 'a prefix too long' => ['10.0.0.0/33'];
        yield 'a non-numeric prefix' => ['10.0.0.0/abc'];
    }

    #[DataProvider('invalidEntries')]
    public function testRefusesAnInvalidProxyEntry(string $entry): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TrustedProxyClientIpResolver([$entry]);
    }
}
