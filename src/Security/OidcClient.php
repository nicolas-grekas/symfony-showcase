<?php

namespace App\Security;

use Jose\Component\Checker;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWKSet;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Calls the OpenID Connect provider of the conference, at the endpoints its discovery document announces.
 */
final class OidcClient
{
    private readonly HttpClientInterface $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly ClockInterface $clock,
        #[Autowire(env: 'OIDC_ISSUER')]
        private readonly string $issuer,
        #[Autowire(env: 'OIDC_CLIENT_ID')]
        private readonly string $clientId,
        #[Autowire(env: 'OIDC_CLIENT_SECRET')]
        #[\SensitiveParameter]
        private readonly string $clientSecret,
    ) {
        // with a trailing slash: the discovery path is resolved against it as a relative URL
        $this->httpClient = $httpClient->withOptions(['base_uri' => $issuer.'/']);
    }

    /**
     * @param array<string, string> $parameters
     */
    public function getAuthorizationUrl(array $parameters): string
    {
        return $this->getEndpoint('authorization_endpoint').'?'.http_build_query(['response_type' => 'code', 'client_id' => $this->clientId] + $parameters);
    }

    /**
     * @return array<string, mixed> The tokens the provider issued for the authorization code
     */
    public function exchangeCode(string $code, string $redirectUri, string $codeVerifier): array
    {
        return $this->requestTokens([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'code_verifier' => $codeVerifier,
        ])->toArray();
    }

    /**
     * @return array<string, mixed>|null The tokens the provider issued for the refresh token, or null when it no longer honors it
     */
    public function refreshTokens(#[\SensitiveParameter] string $refreshToken): ?array
    {
        $response = $this->requestTokens([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        // RFC 6749, Section 5.2: the refresh token expired or was revoked, as when the session at the provider ended
        if (400 === $response->getStatusCode() && 'invalid_grant' === ($response->toArray(false)['error'] ?? null)) {
            return null;
        }

        return $response->toArray();
    }

    /**
     * @return array<string, mixed> The claims of the user the access token was issued for
     */
    public function fetchUserInfo(#[\SensitiveParameter] string $accessToken): array
    {
        return $this->httpClient->request('GET', $this->getEndpoint('userinfo_endpoint'), ['auth_bearer' => $accessToken])->toArray();
    }

    /**
     * Verifies the signature of a token the provider issued to this client, and its "iss", "aud", "iat", "nbf" and "exp" claims.
     *
     * @return array<string, mixed> The claims of the token
     *
     * @throws AuthenticationException When the token cannot be trusted
     */
    public function verify(string $token): array
    {
        try {
            $jws = (new CompactSerializer())->unserialize($token);

            // only RS256 is accepted, so that the public keys of the provider never serve as a shared secret
            if (!(new JWSVerifier(new AlgorithmManager([new RS256()])))->verifyWithKeySet($jws, $this->getKeys(), 0)) {
                throw new AuthenticationException('The signature of the token is invalid.');
            }

            $claims = json_decode($jws->getPayload(), true, flags: \JSON_THROW_ON_ERROR);

            (new Checker\ClaimCheckerManager([
                new Checker\IssuerChecker([$this->issuer]),
                new Checker\AudienceChecker($this->clientId),
                new Checker\IssuedAtChecker($this->clock),
                new Checker\NotBeforeChecker($this->clock),
                new Checker\ExpirationTimeChecker($this->clock),
            ]))->check($claims, ['iss', 'aud', 'iat', 'exp']);
        } catch (\InvalidArgumentException|\JsonException|Checker\ClaimExceptionInterface|HttpClientException $e) {
            throw new AuthenticationException('The token cannot be trusted.', 0, $e);
        }

        return $claims;
    }

    public function getEndSessionUrl(string $idToken, string $postLogoutRedirectUri): string
    {
        return $this->getEndpoint('end_session_endpoint').'?'.http_build_query(['id_token_hint' => $idToken, 'post_logout_redirect_uri' => $postLogoutRedirectUri]);
    }

    /**
     * @param array<string, string> $body
     */
    private function requestTokens(array $body): ResponseInterface
    {
        return $this->httpClient->request('POST', $this->getEndpoint('token_endpoint'), [
            // client_secret_basic: the credentials are form-encoded before being sent with HTTP Basic (RFC 6749, Section 2.3.1)
            'auth_basic' => [urlencode($this->clientId), urlencode($this->clientSecret)],
            'body' => $body,
        ]);
    }

    private function getKeys(): JWKSet
    {
        return JWKSet::createFromKeyData($this->cache->get('oidc.jwks', function (ItemInterface $item): array {
            $item->expiresAfter(3600);

            return $this->httpClient->request('GET', $this->getEndpoint('jwks_uri'))->toArray();
        }));
    }

    private function getEndpoint(string $name): string
    {
        $configuration = $this->cache->get('oidc.configuration', function (ItemInterface $item): array {
            $item->expiresAfter(3600);

            return $this->httpClient->request('GET', '.well-known/openid-configuration')->toArray();
        });

        return $configuration[$name] ?? throw new \LogicException(\sprintf('The OpenID Connect provider announces no "%s".', $name));
    }
}
