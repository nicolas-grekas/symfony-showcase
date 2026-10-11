<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Rejects the access tokens of the api firewall unless the discovery document of the identity provider announces the
 * expected issuer, so that the keys it lists only verify the tokens of that issuer (OpenID Connect Discovery 1.0, Section 4.3).
 */
final class AccessTokenIssuerListener
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(service: 'cache.app')] private readonly CacheInterface $cache,
        #[Autowire(env: 'OIDC_ISSUER')] private readonly string $issuer,
    ) {
    }

    // before the user checker loads the user, which the repository creates on their first request
    #[AsEventListener(dispatcher: 'security.event_dispatcher.api', priority: 512)]
    public function __invoke(CheckPassportEvent $event): void
    {
        try {
            $announcedIssuer = $this->cache->get('oidc_discovery_issuer', function (ItemInterface $item): mixed {
                $item->expiresAfter(3600);

                return $this->httpClient->request('GET', $this->issuer.'/.well-known/openid-configuration')->toArray()['issuer'] ?? null;
            });
        } catch (ExceptionInterface $e) {
            throw new BadCredentialsException('The discovery document of the identity provider could not be fetched.', 0, $e);
        }

        if (!\is_string($announcedIssuer) || rtrim($announcedIssuer, '/') !== rtrim($this->issuer, '/')) {
            throw new BadCredentialsException(\sprintf('The discovery document of the identity provider announces the issuer "%s" instead of "%s".', \is_string($announcedIssuer) ? $announcedIssuer : get_debug_type($announcedIssuer), $this->issuer));
        }
    }
}
