<?php

namespace App\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\AuthenticationTokenCreatedEvent;

/**
 * Keeps the OAuth2 scopes the access token was granted on the security token of the api firewall, as a list.
 */
final class AccessTokenScopeListener
{
    public const ATTRIBUTE = 'scope';

    #[AsEventListener(dispatcher: 'security.event_dispatcher.api')]
    public function __invoke(AuthenticationTokenCreatedEvent $event): void
    {
        // the oidc token handler puts the claims of the access token on the user badge
        $scope = $event->getPassport()->getBadge(UserBadge::class)?->getAttributes()['scope'] ?? '';

        $event->getAuthenticatedToken()->setAttribute(self::ATTRIBUTE, \is_string($scope) ? preg_split('/ +/', $scope, flags: \PREG_SPLIT_NO_EMPTY) : []);
    }
}
