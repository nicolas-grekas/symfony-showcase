<?php

namespace App\Security;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Remembers the sessions the OpenID Connect provider ended, from the logout tokens it posts (OpenID Connect Back-Channel Logout 1.0).
 *
 * The provider calls the application outside of any request of the browser that logged in, so the login is only
 * refused on the next request of that browser, by OidcSessionListener.
 */
final class OidcBackChannelLogout
{
    private const EVENT = 'http://schemas.openid.net/event/backchannel-logout';

    // at least as long as a session of the application can stay idle
    private const LIFETIME = 86400;

    public function __construct(
        private readonly OidcClient $oidcClient,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @throws AuthenticationException When the provider did not issue the logout token to this client
     */
    public function logout(string $logoutToken): void
    {
        $claims = $this->oidcClient->verify($logoutToken);

        // Section 2.6: the token carries the logout event and no nonce, and the client requires the "sid" claim
        if (!isset($claims['events'][self::EVENT]) || isset($claims['nonce']) || !\is_string($claims['sid'] ?? null)) {
            throw new AuthenticationException('The token is not a logout token naming a session.');
        }

        $this->cache->save($this->cache->getItem($this->getKey($claims['sid']))->set(true)->expiresAfter(self::LIFETIME));
    }

    public function hasEnded(string $sid): bool
    {
        return $this->cache->hasItem($this->getKey($sid));
    }

    private function getKey(string $sid): string
    {
        return 'oidc.ended_session.'.hash('xxh128', $sid);
    }
}
