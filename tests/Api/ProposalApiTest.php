<?php

namespace App\Tests\Api;

use App\Entity\Material\Slides;
use App\Entity\Material\Video;
use App\Entity\Proposal;
use App\Enum\ProposalStatus;
use App\Message\ProposalSubmitted;
use App\Tests\MessengerAssertionsTrait;
use App\Tests\Security\FakeIdentityProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpClient\MockHttpClient;

final class ProposalApiTest extends WebTestCase
{
    use MessengerAssertionsTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        // the replaced transport survives the reboots of the kernel between requests
        $this->client = static::createClient(['configure_container' => static function (ContainerInterface $container) {
            $container->set('http_client.transport', new MockHttpClient(new FakeIdentityProvider($_SERVER['OIDC_ISSUER'])));
        }]);
    }

    public function testSubmitAProposal()
    {
        $this->submit(FakeIdentityProvider::issueAccessToken('alice@example.com', 'openid proposals:write'), self::payload());

        $this->assertResponseStatusCodeSame(201);
        $talk = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Streaming JSON with Symfony', $talk['title']);
        $this->assertSame(['Alice Martin'], $talk['speakers']);
        $this->assertResponseHeaderSame('Location', '/api/proposals/'.$talk['id']);
        $this->assertQueuedMessageCount(1, 'outbox', ProposalSubmitted::class);

        $proposal = static::getContainer()->get(EntityManagerInterface::class)->find(Proposal::class, $talk['id']);
        $this->assertSame(ProposalStatus::Submitted, $proposal->getStatus());
        $this->assertSame([Slides::class, Video::class], $proposal->materials->map(get_class(...))->toArray());

        // the speaker reads it back with the same token
        $this->client->request('GET', '/api/proposals/'.$talk['id'], server: ['HTTP_AUTHORIZATION' => 'Bearer '.FakeIdentityProvider::issueAccessToken('alice@example.com')]);
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/api/proposals/'.$talk['id']);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testTheWholeProposalIsValidated()
    {
        $payload = self::payload();
        $payload['speaker']['bio'] = '';
        $payload['materials'][0]['url'] = 'not a url';
        $this->submit(FakeIdentityProvider::issueAccessToken('alice@example.com', 'openid proposals:write'), $payload);

        $this->assertResponseStatusCodeSame(422);
        $violations = json_decode($this->client->getResponse()->getContent(), true)['violations'];
        $this->assertSame(['speaker.bio', 'materials[0].url'], array_column($violations, 'propertyPath'));
    }

    public function testATokenIsRequired()
    {
        $this->submit(null, self::payload());

        $this->assertResponseStatusCodeSame(401);
        $this->assertStringStartsWith('Bearer ', $this->client->getResponse()->headers->get('WWW-Authenticate'));
        $this->assertStringContainsString('resource_metadata="http://localhost/.well-known/oauth-protected-resource"', $this->client->getResponse()->headers->get('WWW-Authenticate'));

        $this->client->request('GET', '/.well-known/oauth-protected-resource');
        $this->assertResponseIsSuccessful();
        $metadata = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame([$_SERVER['OIDC_ISSUER']], $metadata['authorization_servers']);
        $this->assertSame(['proposals:write'], $metadata['scopes_supported']);
    }

    public function testTheProposalsScopeIsRequired()
    {
        $this->submit(FakeIdentityProvider::issueAccessToken('alice@example.com'), self::payload());

        $this->assertResponseStatusCodeSame(403);
        $this->assertStringContainsString('error="insufficient_scope"', $this->client->getResponse()->headers->get('WWW-Authenticate'));
        $this->assertStringContainsString('scope="proposals:write"', $this->client->getResponse()->headers->get('WWW-Authenticate'));
    }

    public function testTokensOfAnotherAudienceAreRejected()
    {
        $token = FakeIdentityProvider::issueAccessToken('alice@example.com', 'openid proposals:write', audience: 'another-api');
        $this->submit($token, self::payload());

        $this->assertResponseStatusCodeSame(401);
    }

    private function submit(?string $token, array $payload): void
    {
        $this->client->request('POST', '/api/conferences/symfonylive-lille/proposals', server: array_filter([
            // any media type with a +json suffix is decoded as JSON
            'CONTENT_TYPE' => 'application/vnd.showcase.proposal+json',
            'HTTP_AUTHORIZATION' => $token ? 'Bearer '.$token : null,
        ]), content: json_encode($payload));
    }

    private static function payload(): array
    {
        return [
            'speaker' => ['name' => 'Alice Martin', 'bio' => 'Alice streams JSON for a living.'],
            'talk' => [
                'title' => 'Streaming JSON with Symfony',
                'abstract' => str_repeat('How JsonStreamer writes large documents. ', 3),
                'level' => 'advanced',
                'format' => 'talk',
            ],
            'materials' => [
                ['type' => 'slides', 'url' => 'https://speakerdeck.com/alice/json'],
                ['type' => 'video', 'url' => 'https://youtube.com/watch?v=json', 'minutes' => 38],
            ],
        ];
    }
}
