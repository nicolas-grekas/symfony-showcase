<?php

namespace App\Tests\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Speakers log in with the identity provider of the conference, which this test fakes.
 */
final class OidcLoginTest extends WebTestCase
{
    private const SPEAKER = [
        'sub' => '5b0f2c1e-8a4d-4f7e-9c3b-2d6e1f0a7b48',
        'email' => 'dan@example.com',
        'email_verified' => true,
        'name' => 'Dan Dupont',
    ];

    private KernelBrowser $client;
    private string $issuer;

    /** @var array<string, string> The parameters of the last authorization request */
    private array $authorizationRequest = [];

    /** @var list<array{authorization: string, body: array<string, string>}> */
    private array $tokenRequests = [];

    /** @var list<string> */
    private array $idTokens = [];

    private int $accessTokenLifetime = 300;
    private bool $refreshTokenRevoked = false;

    protected function setUp(): void
    {
        $this->issuer = $_SERVER['OIDC_ISSUER'];
        // the fake provider answers the requests of every kernel the client boots
        $this->client = static::createClient(['configure_container' => function (ContainerInterface $container) {
            $container->set('http_client.transport', new MockHttpClient($this->answer(...)));
        }]);
        // the discovery document and the keys of the provider are cached
        static::getContainer()->get('cache.app')->clear();
    }

    public function testSpeakersLogInWithTheIdentityProvider()
    {
        $this->client->request('GET', '/login');
        $this->client->clickLink('conference account');

        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertStringStartsWith($this->issuer.'/protocol/openid-connect/auth?', $location);
        parse_str(parse_url($location, \PHP_URL_QUERY), $this->authorizationRequest);
        $this->assertSame('code', $this->authorizationRequest['response_type']);
        $this->assertSame($_SERVER['OIDC_CLIENT_ID'], $this->authorizationRequest['client_id']);
        $this->assertSame('http://localhost/oidc/callback', $this->authorizationRequest['redirect_uri']);
        $this->assertSame('openid profile email', $this->authorizationRequest['scope']);
        $this->assertSame('S256', $this->authorizationRequest['code_challenge_method']);

        // the provider sends the user back with an authorization code
        $this->client->request('GET', '/oidc/callback', ['code' => 'the-code', 'state' => $this->authorizationRequest['state']]);
        $this->assertResponseRedirects('/');

        $this->assertCount(1, $this->tokenRequests);
        ['authorization' => $authorization, 'body' => $body] = $this->tokenRequests[0];
        $this->assertSame('Basic '.base64_encode($_SERVER['OIDC_CLIENT_ID'].':'.$_SERVER['OIDC_CLIENT_SECRET']), $authorization);
        $this->assertSame('authorization_code', $body['grant_type']);
        $this->assertSame('the-code', $body['code']);
        $this->assertSame('http://localhost/oidc/callback', $body['redirect_uri']);
        $this->assertSame($this->authorizationRequest['code_challenge'], rtrim(strtr(base64_encode(hash('sha256', $body['code_verifier'], true)), '+/', '-_'), '='));

        $this->client->followRedirect();
        $this->assertSelectorTextContains('nav', 'Dan Dupont');

        // the user is created on their first login
        $user = static::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['email' => 'dan@example.com']);
        $this->assertSame('Dan Dupont', $user->name);
        $this->assertNull($user->password);
    }

    public function testACallbackForAnotherLoginIsRefused()
    {
        $this->client->request('GET', '/oidc/start');
        $this->client->request('GET', '/oidc/callback', ['code' => 'the-code', 'state' => 'forged']);

        $this->assertResponseRedirects('/login');
        $this->assertSame([], $this->tokenRequests);
        $this->client->followRedirect();
        $this->assertSelectorExists('.flash-error');
        $this->assertSelectorExists('nav a:contains("Log in")');
    }

    public function testAnIdTokenIssuedForAnotherLoginIsRefused()
    {
        $this->client->request('GET', '/oidc/start');
        parse_str(parse_url($this->client->getResponse()->headers->get('Location'), \PHP_URL_QUERY), $this->authorizationRequest);
        $this->authorizationRequest['nonce'] = 'another-nonce';
        $this->client->request('GET', '/oidc/callback', ['code' => 'the-code', 'state' => $this->authorizationRequest['state']]);

        $this->assertResponseRedirects('/login');
        $this->client->followRedirect();
        $this->assertSelectorExists('.flash-error');
        $this->assertSelectorExists('nav a:contains("Log in")');
    }

    public function testLoggingOutEndsTheSessionAtTheIdentityProvider()
    {
        $this->logIn();

        $this->client->request('GET', '/');
        $this->client->submitForm('Log out');

        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertStringStartsWith($this->issuer.'/protocol/openid-connect/logout?', $location);
        parse_str(parse_url($location, \PHP_URL_QUERY), $query);
        $this->assertSame(end($this->idTokens), $query['id_token_hint']);
        $this->assertSame('http://localhost/', $query['post_logout_redirect_uri']);

        $this->client->request('GET', '/');
        $this->assertSelectorExists('nav a:contains("Log in")');
    }

    public function testTheIdentityProviderEndsTheSession()
    {
        $this->logIn();

        // a token that is not a logout token
        $this->client->request('POST', '/oidc/backchannel-logout', ['logout_token' => end($this->idTokens)]);
        $this->assertResponseStatusCodeSame(400);

        $this->client->request('POST', '/oidc/backchannel-logout', ['logout_token' => $this->issueLogoutToken('another-session')]);
        $this->assertResponseIsSuccessful();
        $this->client->request('GET', '/me/tickets');
        $this->assertResponseIsSuccessful();

        $this->client->request('POST', '/oidc/backchannel-logout', ['logout_token' => $this->issueLogoutToken('the-session')]);
        $this->assertResponseIsSuccessful();

        $this->client->followRedirects();
        $this->client->request('GET', '/me/tickets');
        $this->assertRouteSame('app_login');
        $this->assertSelectorExists('nav a:contains("Log in")');
    }

    public function testTheAccessTokenIsRenewedUntilTheIdentityProviderRefuses()
    {
        $this->accessTokenLifetime = 10;
        $this->logIn();

        $this->client->request('GET', '/');
        $this->assertSelectorTextContains('nav', 'Dan Dupont');

        // each request renewed the access token with the refresh token the provider issued last
        $this->assertSame(['authorization_code', 'refresh_token', 'refresh_token'], array_map(static fn (array $request) => $request['body']['grant_type'], $this->tokenRequests));
        $this->assertSame([null, 'refresh-1', 'refresh-2'], array_map(static fn (array $request) => $request['body']['refresh_token'] ?? null, $this->tokenRequests));

        $this->refreshTokenRevoked = true;
        $this->client->followRedirects();
        $this->client->request('GET', '/me/tickets');
        $this->assertRouteSame('app_login');
        $this->assertSelectorExists('nav a:contains("Log in")');
    }

    private function logIn(): void
    {
        $this->client->request('GET', '/oidc/start');
        parse_str(parse_url($this->client->getResponse()->headers->get('Location'), \PHP_URL_QUERY), $this->authorizationRequest);
        $this->client->request('GET', '/oidc/callback', ['code' => 'the-code', 'state' => $this->authorizationRequest['state']]);
        $this->client->followRedirect();
        $this->assertSelectorTextContains('nav', 'Dan Dupont');
    }

    private function answer(string $method, string $url, array $options): MockResponse
    {
        $endpoint = $this->issuer.'/protocol/openid-connect/';

        return match ($url) {
            $this->issuer.'/.well-known/openid-configuration' => new JsonMockResponse([
                'issuer' => $this->issuer,
                'authorization_endpoint' => $endpoint.'auth',
                'token_endpoint' => $endpoint.'token',
                'userinfo_endpoint' => $endpoint.'userinfo',
                'end_session_endpoint' => $endpoint.'logout',
                'jwks_uri' => $endpoint.'certs',
                'id_token_signing_alg_values_supported' => ['RS256'],
            ]),
            $endpoint.'certs' => new JsonMockResponse(['keys' => [self::key()->toPublic()->all()]]),
            $endpoint.'token' => $this->answerTokenRequest($options),
            $endpoint.'userinfo' => new JsonMockResponse(self::SPEAKER),
            default => new MockResponse('', ['http_code' => 404]),
        };
    }

    private function answerTokenRequest(array $options): MockResponse
    {
        parse_str($options['body'], $body);
        $this->tokenRequests[] = [
            'authorization' => substr($options['normalized_headers']['authorization'][0] ?? '', \strlen('Authorization: ')),
            'body' => $body,
        ];

        if ('refresh_token' === $body['grant_type'] && $this->refreshTokenRevoked) {
            return new JsonMockResponse(['error' => 'invalid_grant'], ['http_code' => 400]);
        }

        $now = time();
        $this->idTokens[] = $idToken = self::sign([
            'iss' => $this->issuer,
            'aud' => $_SERVER['OIDC_CLIENT_ID'],
            'azp' => $_SERVER['OIDC_CLIENT_ID'],
            'iat' => $now,
            'exp' => $now + 300,
            'auth_time' => $now,
            'sid' => 'the-session',
        ] + ('authorization_code' === $body['grant_type'] ? ['nonce' => $this->authorizationRequest['nonce']] : []) + self::SPEAKER);

        return new JsonMockResponse([
            'access_token' => 'access-'.\count($this->tokenRequests),
            'token_type' => 'Bearer',
            'expires_in' => $this->accessTokenLifetime,
            'refresh_token' => 'refresh-'.\count($this->tokenRequests),
            'id_token' => $idToken,
        ]);
    }

    private function issueLogoutToken(string $sid): string
    {
        $now = time();

        return self::sign([
            'iss' => $this->issuer,
            'aud' => $_SERVER['OIDC_CLIENT_ID'],
            'iat' => $now,
            'exp' => $now + 300,
            'jti' => bin2hex(random_bytes(8)),
            'sub' => self::SPEAKER['sub'],
            'sid' => $sid,
            'events' => ['http://schemas.openid.net/event/backchannel-logout' => new \stdClass()],
        ], 'logout+jwt');
    }

    private static function sign(array $claims, string $type = 'JWT'): string
    {
        $jws = (new JWSBuilder(new AlgorithmManager([new RS256()])))
            ->create()
            ->withPayload(json_encode($claims))
            ->addSignature(self::key(), ['alg' => 'RS256', 'typ' => $type, 'kid' => self::key()->get('kid')])
            ->build();

        return (new CompactSerializer())->serialize($jws);
    }

    private static function key(): JWK
    {
        static $key;

        return $key ??= JWK::createFromJson(file_get_contents(__DIR__.'/../Fixtures/oidc-signing-key.json'));
    }
}
