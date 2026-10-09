<?php

namespace App\Tests\Security;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Answers the discovery and JWKS requests of the access token handler in tests, and mints the tokens it verifies.
 */
final class FakeIdentityProvider
{
    public function __construct(
        private readonly string $issuer,
    ) {
    }

    public function __invoke(string $method, string $url): MockResponse
    {
        return match ($url) {
            $this->issuer.'/.well-known/openid-configuration' => new JsonMockResponse([
                'issuer' => $this->issuer,
                'jwks_uri' => $this->issuer.'/protocol/openid-connect/certs',
                'id_token_signing_alg_values_supported' => ['RS256'],
            ]),
            $this->issuer.'/protocol/openid-connect/certs' => new JsonMockResponse(['keys' => [self::key()->toPublic()->all()]]),
            default => new MockResponse('', ['http_code' => 404]),
        };
    }

    public static function issueAccessToken(string $email, string $scope = 'openid email profile', string $audience = 'showcase-api'): string
    {
        $now = time();
        $jws = (new JWSBuilder(new AlgorithmManager([new RS256()])))
            ->create()
            ->withPayload(json_encode([
                'iss' => $_SERVER['OIDC_ISSUER'],
                'aud' => $audience,
                'sub' => hash('xxh128', $email),
                'email' => $email,
                'scope' => $scope,
                'iat' => $now,
                'nbf' => $now,
                'exp' => $now + 300,
            ]))
            ->addSignature(self::key(), ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => self::key()->get('kid')])
            ->build();

        return (new CompactSerializer())->serialize($jws);
    }

    private static function key(): JWK
    {
        static $key;

        return $key ??= JWK::createFromJson(file_get_contents(__DIR__.'/../Fixtures/oidc-signing-key.json'));
    }
}
