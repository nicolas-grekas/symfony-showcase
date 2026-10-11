<?php

namespace App\Tests\HttpCache;

use App\HttpCache\CacheStatusHttpCache;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;
use Symfony\Component\HttpKernel\HttpCache\Store;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class CacheStatusHttpCacheTest extends TestCase
{
    private string $storeDir;

    protected function setUp(): void
    {
        $this->storeDir = sys_get_temp_dir().'/http-cache-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->storeDir);
    }

    public function testMissesAndHitsAreReported()
    {
        $cache = $this->createHttpCache(new Response('[]', headers: ['Cache-Control' => 'public, max-age=300']));

        $this->assertSame('Symfony; fwd=miss; stored', $cache->handle(Request::create('/api/conferences'))->headers->get('Cache-Status'));
        $this->assertMatchesRegularExpression('/^Symfony; hit; ttl=\d+$/', $cache->handle(Request::create('/api/conferences'))->headers->get('Cache-Status'));
    }

    public function testRequestsForwardedForTheirMethodAreReported()
    {
        $cache = $this->createHttpCache(new Response('', 201));

        $this->assertSame('Symfony; fwd=method', $cache->handle(Request::create('/api/conferences/symfonylive-lille/proposals', 'POST'))->headers->get('Cache-Status'));
    }

    private function createHttpCache(Response $response): CacheStatusHttpCache
    {
        $backend = new class($response) implements HttpKernelInterface {
            public function __construct(
                private readonly Response $response,
            ) {
            }

            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return clone $this->response;
            }
        };

        return new CacheStatusHttpCache(new HttpCache($backend, new Store($this->storeDir)));
    }
}
