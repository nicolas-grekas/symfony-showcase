<?php

namespace App\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;

/**
 * Lets the firewall switch the user, or switch back, only on a POST request carrying a CSRF token.
 */
#[AsEventListener]
final class SwitchUserCsrfListener
{
    public const TOKEN_ID = 'switch_user';

    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(SwitchUserEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->isMethod('POST') || !$this->csrfTokenManager->isTokenValid(new CsrfToken(self::TOKEN_ID, $request->request->getString('_csrf_token')))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }
    }
}
