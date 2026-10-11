<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;

/**
 * Logs the speakers in with the OpenID Connect provider of the conference, with the authorization code flow and PKCE.
 *
 * The user provider of the firewall loads the users from their "email" claim, and creates the ones who log in for
 * the first time from the other claims. The tokens of the provider are kept in the "oidc" attribute of the
 * security token, for OidcSessionListener to renew them and to end the session at the provider on logout.
 */
final class OidcAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface, InteractiveAuthenticatorInterface
{
    use TargetPathTrait;

    private const SESSION_KEY = '_security.oidc_login';

    public function __construct(
        private readonly OidcClient $oidcClient,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Sends the user to the provider, keeping in the session what its answer must match.
     */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        $state = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(32));
        $codeVerifier = bin2hex(random_bytes(32));
        $redirectUri = $this->urlGenerator->generate('oidc_login_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $request->getSession()->set(self::SESSION_KEY, [
            'state' => $state,
            'nonce' => $nonce,
            'code_verifier' => $codeVerifier,
            'redirect_uri' => $redirectUri,
        ]);

        return new RedirectResponse($this->oidcClient->getAuthorizationUrl([
            'redirect_uri' => $redirectUri,
            'scope' => 'openid profile email',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]));
    }

    public function supports(Request $request): bool
    {
        return 'oidc_login_callback' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        // removed whatever happens next, so that the answer of the provider cannot be replayed
        $login = $request->getSession()->remove(self::SESSION_KEY);

        if (!\is_array($login) || !hash_equals($login['state'], $request->query->getString('state'))) {
            throw new AuthenticationException('The OpenID Connect callback does not answer the login of this browser.');
        }

        if ($request->query->has('error')) {
            throw new AuthenticationException(\sprintf('The OpenID Connect provider refused the login: "%s".', $request->query->getString('error')));
        }

        try {
            $tokens = $this->oidcClient->exchangeCode($request->query->getString('code'), $login['redirect_uri'], $login['code_verifier']);
            $idToken = $this->oidcClient->verify($tokens['id_token']);
            $claims = $this->oidcClient->fetchUserInfo($tokens['access_token']);
        } catch (HttpClientException $e) {
            throw new AuthenticationException('The OpenID Connect provider cannot be reached.', 0, $e);
        }

        if (!hash_equals($login['nonce'], (string) ($idToken['nonce'] ?? ''))) {
            throw new AuthenticationException('The ID token was not issued for the login of this browser.');
        }

        // OpenID Connect Core 1.0, Section 5.3.2
        if (($claims['sub'] ?? null) !== $idToken['sub'] || !\is_string($claims['email'] ?? null)) {
            throw new AuthenticationException('The UserInfo endpoint does not describe the user of the ID token.');
        }

        // the claims are passed to the user provider, which creates the users who log in for the first time
        $passport = new SelfValidatingPassport(new UserBadge($claims['email'], null, $claims));
        $passport->setAttribute('oidc', [
            'id_token' => $tokens['id_token'],
            'access_token' => $tokens['access_token'],
            'expires_at' => isset($tokens['expires_in']) ? time() + $tokens['expires_in'] : null,
            'refresh_token' => $tokens['refresh_token'] ?? null,
            // the session at the provider, which its back-channel logout names
            'sid' => $idToken['sid'] ?? null,
        ]);

        return $passport;
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $token = parent::createToken($passport, $firewallName);
        $token->setAttribute('oidc', $passport->getAttribute('oidc'));

        return $token;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            $this->removeTargetPath($request->getSession(), $firewallName);

            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        // the login page shows the error
        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    public function isInteractive(): bool
    {
        return true;
    }
}
