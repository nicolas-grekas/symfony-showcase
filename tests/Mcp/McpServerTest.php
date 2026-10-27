<?php

namespace App\Tests\Mcp;

use App\Entity\Proposal;
use App\Entity\User;
use App\Enum\ProposalStatus;
use App\Enum\Room;
use App\Message\ProposalSubmitted;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Security\Http\Authenticator\AccessTokenAuthenticator;

final class McpServerTest extends WebTestCase
{
    private const PROTOCOL_VERSION = '2025-11-25';

    private KernelBrowser $client;
    private ?string $sessionId = null;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testAnAccessTokenIsRequired()
    {
        $this->rpc('initialize', self::initializeParams());

        $this->assertResponseStatusCodeSame(401);
        $this->assertSame('Bearer resource_metadata="http://localhost/.well-known/oauth-protected-resource"', $this->client->getResponse()->headers->get('WWW-Authenticate'));
    }

    public function testTheMetadataTellsWhereToGetAToken()
    {
        $this->client->request('GET', '/.well-known/oauth-protected-resource');

        $this->assertResponseIsSuccessful();
        $metadata = json_decode($this->client->getResponse()->getContent(), true);
        // the resource is the origin, which covers the MCP endpoint and the API alike
        $this->assertSame('http://localhost', $metadata['resource']);
        $this->assertSame([$_SERVER['OIDC_ISSUER']], $metadata['authorization_servers']);
        $this->assertSame(['proposals:write'], $metadata['scopes_supported']);
    }

    public function testInitializeAndListTheTools()
    {
        $this->login('alice@example.com');

        $result = $this->initialize();
        $this->assertSame(self::PROTOCOL_VERSION, $result['protocolVersion']);
        $this->assertSame('conferences', $result['serverInfo']['name']);
        $this->assertArrayHasKey('tools', $result['capabilities']);
        $this->assertNotNull($this->sessionId);

        $tools = array_column($this->call('tools/list')['tools'], null, 'name');
        $this->assertSame(['search_talks', 'get_schedule', 'list_my_proposals', 'submit_proposal'], array_keys($tools));
        $this->assertTrue($tools['search_talks']['annotations']['readOnlyHint']);
        $this->assertSame(['beginner', 'intermediate', 'advanced'], $tools['submit_proposal']['inputSchema']['properties']['level']['enum']);
    }

    public function testSearchTheAcceptedTalks()
    {
        $this->login('alice@example.com');
        $this->initialize();

        $result = $this->call('tools/call', ['name' => 'search_talks', 'arguments' => ['query' => 'WORKFLOW']]);

        $this->assertFalse($result['isError'] ?? false);
        $talks = $result['structuredContent']['talks'];
        $this->assertSame(['Workflows as code'], array_column($talks, 'title'));
        $this->assertSame('symfonycon-amsterdam', $talks[0]['conference']);
        $this->assertSame(['Alice Martin'], $talks[0]['speakers']);
    }

    public function testReadTheScheduleOfAConference()
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $talk = $entityManager->getRepository(Proposal::class)->findOneBy(['title' => 'Workflows as code']);
        $talk->schedule(new DatePoint($talk->conference->startsOn->format('Y-m-d').' 09:30'), Room::Main);
        $entityManager->flush();

        $this->login('carol@example.com');
        $this->initialize();
        $result = $this->call('tools/call', ['name' => 'get_schedule', 'arguments' => ['conference' => 'symfonycon-amsterdam']]);

        $schedule = $result['structuredContent'];
        $this->assertSame('SymfonyCon Amsterdam', $schedule['conference']);
        $this->assertSame([[
            'startsAt' => $talk->scheduledAt->format(\DATE_ATOM),
            'room' => 'main',
            'title' => 'Workflows as code',
            'speakers' => ['Alice Martin'],
        ]], $schedule['slots']);
    }

    public function testTheScheduleIsAlsoAResource()
    {
        $this->login('carol@example.com');
        $this->initialize();

        $templates = $this->call('resources/templates/list')['resourceTemplates'];
        $this->assertSame(['conference://{conference}/schedule'], array_column($templates, 'uriTemplate'));

        $contents = $this->call('resources/read', ['uri' => 'conference://symfonycon-amsterdam/schedule'])['contents'];
        $this->assertSame('application/json', $contents[0]['mimeType']);
        $this->assertSame(['conference' => 'SymfonyCon Amsterdam', 'slots' => []], json_decode($contents[0]['text'], true));
    }

    public function testAnUnknownConferenceIsAToolError()
    {
        $this->login('alice@example.com');
        $this->initialize();

        $result = $this->call('tools/call', ['name' => 'get_schedule', 'arguments' => ['conference' => 'nope']]);

        $this->assertTrue($result['isError']);
        $this->assertSame('There is no conference "nope". The upcoming conferences are: symfonycon-amsterdam, symfonylive-lille.', $result['content'][0]['text']);
    }

    public function testListMyProposals()
    {
        $this->login('alice@example.com');
        $this->initialize();

        $result = $this->call('tools/call', ['name' => 'list_my_proposals']);

        $this->assertSame([
            ['conference' => 'symfonylive-lille', 'title' => 'Forms in 2027', 'status' => 'submitted'],
            ['conference' => 'symfonycon-amsterdam', 'title' => 'Workflows as code', 'status' => 'accepted'],
        ], array_map(static fn (array $p) => array_intersect_key($p, array_flip(['conference', 'title', 'status'])), $result['structuredContent']['proposals']));
    }

    public function testSubmitAProposal()
    {
        $this->login('alice@example.com', ['proposals:write']);
        $this->initialize();

        $result = $this->call('tools/call', ['name' => 'submit_proposal', 'arguments' => self::proposal()]);

        $this->assertFalse($result['isError'] ?? false, $result['content'][0]['text'] ?? '');
        $proposal = $result['structuredContent'];
        $this->assertSame('MCP servers with Symfony', $proposal['title']);
        $this->assertSame('submitted', $proposal['status']);
        $this->assertQueuedMessageCount(1, 'events.outbox', ProposalSubmitted::class);

        $entity = static::getContainer()->get(EntityManagerInterface::class)->find(Proposal::class, $proposal['id']);
        $this->assertSame(ProposalStatus::Submitted, $entity->getStatus());
        $this->assertSame('alice@example.com', $entity->speakers->first()->email);
    }

    public function testTheProposalIsValidated()
    {
        $this->login('alice@example.com', ['proposals:write']);
        $this->initialize();

        $result = $this->call('tools/call', ['name' => 'submit_proposal', 'arguments' => ['abstract' => 'Too short.'] + self::proposal()]);

        $this->assertTrue($result['isError']);
        $this->assertSame('The proposal is not valid: talk.abstract: This value is too short. It should have 50 characters or more.', $result['content'][0]['text']);
    }

    public function testProposalsAreOnlyTakenWhileTheCallForPapersIsOpen()
    {
        $this->login('alice@example.com', ['proposals:write']);
        $this->initialize();

        $result = $this->call('tools/call', ['name' => 'submit_proposal', 'arguments' => ['conference' => 'symfonycon-amsterdam'] + self::proposal()]);

        $this->assertTrue($result['isError']);
        $this->assertSame('The call for papers of SymfonyCon Amsterdam is closed.', $result['content'][0]['text']);
    }

    public function testSubmittingAProposalRequiresTheScope()
    {
        $this->login('alice@example.com');
        $this->initialize();

        $this->rpc('tools/call', ['name' => 'submit_proposal', 'arguments' => self::proposal()]);

        // the challenge of RFC 6750, which MCP clients answer by asking the user for the missing scope
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame('Bearer error="insufficient_scope",error_description="The request requires higher privileges than provided by the access token.",scope="proposals:write",resource_metadata="http://localhost/.well-known/oauth-protected-resource"', $this->client->getResponse()->headers->get('WWW-Authenticate'));
        $this->assertSame(0, static::getContainer()->get(EntityManagerInterface::class)->getRepository(Proposal::class)->count(['title' => 'MCP servers with Symfony']));
    }

    public function testTheOtherToolsDoNotNeedTheScope()
    {
        $this->login('alice@example.com');
        $this->initialize();

        $result = $this->call('tools/call', ['name' => 'search_talks', 'arguments' => ['query' => 'profiling']]);

        $this->assertSame(['Profiling a Symfony app end to end'], array_column($result['structuredContent']['talks'], 'title'));
    }

    private function login(string $email, array $scopes = []): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        if (!$user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email])) {
            $entityManager->persist($user = new User($email, ucfirst(strstr($email, '@', true))));
            $entityManager->flush();
        }

        $this->client->loginUser($user, 'api', [AccessTokenAuthenticator::SCOPE_ATTRIBUTE => $scopes]);
    }

    private function initialize(): array
    {
        $result = $this->call('initialize', self::initializeParams());
        $this->sessionId = $this->client->getResponse()->headers->get('Mcp-Session-Id');

        $this->rpc('notifications/initialized');
        $this->assertResponseStatusCodeSame(202);

        return $result;
    }

    private function call(string $method, array $params = []): array
    {
        $this->rpc($method, $params);

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('result', $response, json_encode($response));

        return $response['result'];
    }

    private function rpc(string $method, array $params = []): void
    {
        static $id = 0;

        // a notification has no id
        $message = ['jsonrpc' => '2.0'] + (str_starts_with($method, 'notifications/') ? [] : ['id' => ++$id]) + ['method' => $method];
        if ($params) {
            $message['params'] = $params;
        }

        $this->client->request('POST', '/mcp', server: array_filter([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_MCP_PROTOCOL_VERSION' => $this->sessionId ? self::PROTOCOL_VERSION : null,
            'HTTP_MCP_SESSION_ID' => $this->sessionId,
        ]), content: json_encode($message));
    }

    private static function initializeParams(): array
    {
        return [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => new \stdClass(),
            'clientInfo' => ['name' => 'phpunit', 'version' => '1.0'],
        ];
    }

    private static function proposal(): array
    {
        return [
            'conference' => 'symfonylive-lille',
            'title' => 'MCP servers with Symfony',
            'abstract' => str_repeat('How the MCP bundle exposes the services of an app. ', 2),
            'level' => 'intermediate',
            'format' => 'talk',
            'speakerName' => 'Alice Martin',
            'bio' => 'Alice builds Symfony apps for a living.',
        ];
    }
}
