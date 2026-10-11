<?php

namespace App\Security;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Asks the users for their password again before the actions that require a recent login.
 *
 * The time of each interactive login is kept on the security token. A voter denying access for lack of a recent
 * login says so with requestReAuthentication(), and the firewall, which uses this class as its access denied
 * handler, then sends the user to the login form instead of answering with a 403.
 */
final class RecentAuthentication implements AccessDeniedHandlerInterface
{
    use ClockAwareTrait;
    use TargetPathTrait;

    // how long an interactive login counts as recent, in seconds
    private const LIFETIME = 900;
    private const TOKEN_ATTRIBUTE = 'authenticated_at';
    private const SESSION_KEY = '_security.re_authentication';

    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function isRecent(TokenInterface $token): bool
    {
        return $token->hasAttribute(self::TOKEN_ATTRIBUTE) && $this->now()->getTimestamp() - $token->getAttribute(self::TOKEN_ATTRIBUTE) < self::LIFETIME;
    }

    /**
     * Tells the firewall to ask for the password again instead of denying access.
     */
    public function requestReAuthentication(?Vote $vote): void
    {
        if (null !== $vote) {
            $vote->extraData[self::class] = true;
        }
    }

    /**
     * Whether the login form asks the logged-in user to confirm their password.
     */
    public function isReAuthenticating(Request $request): bool
    {
        return $request->getSession()->get(self::SESSION_KEY, false);
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        foreach ($accessDeniedException->getAccessDecision()?->votes ?? [] as $vote) {
            if (VoterInterface::ACCESS_DENIED === $vote->result && ($vote->extraData[self::class] ?? false)) {
                return $this->startReAuthentication($request);
            }
        }

        return null;
    }

    #[AsEventListener]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $authenticator = $event->getAuthenticator();

        if ($authenticator instanceof InteractiveAuthenticatorInterface && $authenticator->isInteractive()) {
            $event->getAuthenticatedToken()->setAttribute(self::TOKEN_ATTRIBUTE, $this->now()->getTimestamp());
            $event->getRequest()->getSession()->remove(self::SESSION_KEY);
        }
    }

    private function startReAuthentication(Request $request): Response
    {
        $session = $request->getSession();
        $session->set(self::SESSION_KEY, true);
        // the login form fills in the identifier of the logged-in user
        $session->set(SecurityRequestAttributes::LAST_USERNAME, $this->security->getUser()->getUserIdentifier());

        if ($request->isMethodSafe()) {
            $this->saveTargetPath($session, $this->security->getFirewallConfig($request)->getName(), $request->getUri());
        }

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
