<?php

namespace App\HttpCache;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tells what the HTTP cache of the kernel did in an RFC 9211 "Cache-Status" header.
 */
#[AsDecorator('http_cache')]
final class CacheStatusHttpCache implements HttpKernelInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly HttpCache $httpCache,
    ) {
    }

    public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
    {
        $response = $this->httpCache->handle($request, $type, $catch);

        if (self::MAIN_REQUEST === $type && $parameters = $this->getParameters($request, $response)) {
            // appended to the members added by the caches closer to the origin
            $response->headers->set('Cache-Status', 'Symfony; '.implode('; ', $parameters), false);
        }

        return $response;
    }

    /**
     * @return list<string>
     */
    private function getParameters(Request $request, Response $response): array
    {
        // the events of the main request come before the ones of its fragments
        $traces = $this->httpCache->getTraces();
        $events = $traces[array_key_first($traces)] ?? [];
        $has = static fn (string $event): bool => \in_array($event, $events, true);

        // the cache builds this 503 on its own when the backend stays locked, the RFC asks not to report it
        if ($has('waiting') && 503 === $response->getStatusCode()) {
            return [];
        }

        if ($has('fresh') || $has('stale-while-revalidate')) {
            return null === ($maxAge = $response->getMaxAge()) ? ['hit'] : ['hit', 'ttl='.($maxAge - $response->getAge())];
        }

        $parameters = match (true) {
            $has('pass') => [$request->isMethodCacheable() ? 'fwd=bypass' : 'fwd=method'],
            $has('reload') => ['fwd=request'],
            $has('valid'), $has('invalid'), $has('stale-if-error') => ['fwd=stale'],
            $has('miss') => ['fwd=miss'],
            default => [],
        };

        if (!$parameters) {
            return [];
        }

        // the backend answered 304 to the revalidation of the stored response
        if ($has('valid') && 304 !== $response->getStatusCode()) {
            $parameters[] = 'fwd-status=304';
        }

        if ($has('store')) {
            $parameters[] = 'stored';
        }

        return $parameters;
    }
}
