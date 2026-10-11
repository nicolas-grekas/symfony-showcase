<?php

namespace App\Security;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;

/**
 * Keeps the logins made with the OpenID Connect provider in line with the sessions it holds.
 *
 * It renews their access token before it expires, logs out the ones whose session the provider ended or whose
 * refresh token it no longer honors, and ends the session at the provider when the user logs out.
 */
final class OidcSessionListener
{
    // the access token is renewed this many seconds before it expires
    private const LEEWAY = 30;

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly OidcClient $oidcClient,
        private readonly OidcBackChannelLogout $backChannelLogout,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    // after the firewall, which restores the security token from the session
    #[AsEventListener(priority: 7)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$event->getRequest()->hasPreviousSession()) {
            return;
        }

        if (!($token = $this->tokenStorage->getToken())?->hasAttribute('oidc')) {
            return;
        }

        $oidc = $token->getAttribute('oidc');

        if (null !== $oidc['sid'] && $this->backChannelLogout->hasEnded($oidc['sid'])) {
            $this->logOut($event);

            return;
        }

        if (null === $oidc['refresh_token'] || null === $oidc['expires_at'] || time() < $oidc['expires_at'] - self::LEEWAY) {
            return;
        }

        try {
            $tokens = $this->oidcClient->refreshTokens($oidc['refresh_token']);
            $idToken = isset($tokens['id_token']) ? $this->oidcClient->verify($tokens['id_token']) : null;
        } catch (HttpClientException|AuthenticationException $e) {
            // the renewal is tried again on the next request
            $this->logger->warning('The access token of the OpenID Connect provider could not be renewed.', ['exception' => $e]);

            return;
        }

        if (null === $tokens) {
            $this->logOut($event);

            return;
        }

        $oidc = [
            'access_token' => $tokens['access_token'],
            'expires_at' => isset($tokens['expires_in']) ? time() + $tokens['expires_in'] : null,
            // the provider may rotate the refresh token
            'refresh_token' => $tokens['refresh_token'] ?? $oidc['refresh_token'],
        ] + $oidc;

        if (null !== $idToken) {
            $oidc['id_token'] = $tokens['id_token'];
            $oidc['sid'] = $idToken['sid'] ?? null;
        }

        $token->setAttribute('oidc', $oidc);
    }

    // before DefaultLogoutListener, which redirects to the target of the firewall
    #[AsEventListener(priority: 65)]
    public function onLogout(LogoutEvent $event): void
    {
        if (null !== $event->getResponse() || !$event->getToken()?->hasAttribute('oidc')) {
            return;
        }

        try {
            $endSessionUrl = $this->oidcClient->getEndSessionUrl(
                $event->getToken()->getAttribute('oidc')['id_token'],
                $this->urlGenerator->generate('home', [], UrlGeneratorInterface::ABSOLUTE_URL),
            );
        } catch (HttpClientException $e) {
            // logging out of the application does not depend on the provider being reachable
            $this->logger->warning('The session at the OpenID Connect provider cannot be ended.', ['exception' => $e]);

            return;
        }

        $event->setResponse(new RedirectResponse($endSessionUrl));
    }

    private function logOut(RequestEvent $event): void
    {
        $this->tokenStorage->setToken(null);
        $request = $event->getRequest();

        // the firewall granted the request to the user who is now logged out: a page is requested again as a
        // visitor, while a form submission sends to the login form
        if (!$request->isMethodSafe()) {
            throw new InsufficientAuthenticationException('The session at the OpenID Connect provider has ended.');
        }

        $event->setResponse(new RedirectResponse($request->getUri()));
    }
}
