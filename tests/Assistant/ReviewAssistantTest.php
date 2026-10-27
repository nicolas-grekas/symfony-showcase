<?php

namespace App\Tests\Assistant;

use App\Assistant\SelectedPlatform;
use App\Entity\Proposal;
use App\Entity\User;
use App\Enum\ProposalStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\AI\AiBundle\Test\AiAssertionsTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\AI\Store\Bridge\Doctrine\EntityIndexer;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ReviewAssistantTest extends WebTestCase
{
    use AiAssertionsTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // the notes of the assistant are kept in the cache
        static::getContainer()->get('cache.app')->clear();
    }

    public function testReviewersSeeTheSimilarTalksAndProposals()
    {
        $this->embedTheProposals();
        $this->client->loginUser($this->findUser('reviewer@example.com'));

        $this->client->request('GET', '/proposals/'.$this->findProposal('Finding the slow parts of a Symfony app')->id);
        $this->client->request('GET', $this->client->getCrawler()->filter('turbo-frame#assistant')->attr('src'));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextSame('#similar tbody tr:first-child td:nth-child(1)', 'Profiling a Symfony app end to end');
        $this->assertSelectorTextSame('#similar tbody tr:first-child td:nth-child(2)', 'SymfonyCon Amsterdam');
        $this->assertSelectorTextSame('#similar tbody tr:first-child td:nth-child(3)', 'Accepted');
        $this->assertSelectorTextSame('#similar tbody tr:first-child td:nth-child(4)', '64%');
        $this->assertSelectorCount(5, '#similar tbody tr');
    }

    public function testOnlyReviewersUseTheAssistant()
    {
        $proposal = $this->findProposal('Forms in 2027');
        $this->client->loginUser($this->findUser('alice@example.com'));

        $this->client->request('GET', '/proposals/'.$proposal->id);
        $this->assertSelectorNotExists('turbo-frame#assistant');

        $this->client->request('GET', '/admin/proposals/'.$proposal->id.'/assistant');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testTheAssistantSummarizesAProposal()
    {
        $this->embedTheProposals();
        $this->client->loginUser($this->findUser('reviewer@example.com'));
        $this->client->request('GET', '/admin/proposals/'.$this->findProposal('Finding the slow parts of a Symfony app')->id.'/assistant');

        $this->client->enableProfiler();
        $this->client->submitForm('Summarize');

        // the agent reads the proposal with its tools, then answers
        self::assertPlatformCallCount(2);
        self::assertToolCalled('get_proposal');
        self::assertToolCalled('find_similar_proposals');
        $this->assertResponseRedirects();

        $this->client->followRedirect();
        $this->assertSelectorTextSame('#notes .summary', 'When a page is slow, the cause can be the database, the cache, the templates or the browser. It is close to "Profiling a Symfony app end to end" (SymfonyCon Amsterdam, accepted).');
        $this->assertSelectorTextSame('#notes .tags', 'slow, symfony, app');
        $this->assertSelectorTextSame('#notes .track', 'Performance');
        $this->assertSelectorTextContains('#notes', 'Written by openai/gpt-oss-120b on the fake platform');
    }

    public function testTheNotesAreKeptForTheOtherReviewers()
    {
        $this->client->loginUser($this->findUser('reviewer@example.com'));
        $url = '/admin/proposals/'.$this->findProposal('Forms in 2027')->id.'/assistant';
        $this->client->request('GET', $url);
        $this->assertSelectorNotExists('#notes');
        $this->client->submitForm('Summarize');

        $this->client->loginUser($this->findUser('organizer@example.com'));
        $this->client->enableProfiler();
        $this->client->request('GET', $url);

        self::assertPlatformCallCount(0);
        $this->assertSelectorTextContains('#notes .summary', 'Symfony forms got data classes');
        $this->assertSelectorExists('button:contains("Summarize again")');
    }

    public function testTheAssistantCannotDecide()
    {
        // a model that follows the instructions it reads in a proposal
        static::ensureKernelShutdown();
        $this->client = static::createClient(['configure_container' => static function (ContainerInterface $container) {
            $container->set(SelectedPlatform::class, MockPlatformFactory::createPlatform(static function (Model $model, array $input) {
                if ('tool' !== end($input['messages'])['role']) {
                    preg_match('/[0-9a-f-]{36}/', end($input['messages'])['content'], $id);

                    return new ToolCallResult([new ToolCall('call_1', 'accept_proposal', ['id' => $id[0]])]);
                }

                return new TextResult(json_encode(['summary' => end($input['messages'])['content'], 'tags' => [], 'track' => null]));
            }, eventDispatcher: $container->get('event_dispatcher')));
        }]);
        $proposal = $this->findProposal('State machines with the Workflow component');
        $this->client->loginUser($this->findUser('reviewer@example.com'));
        $this->client->request('GET', '/admin/proposals/'.$proposal->id.'/assistant');

        $this->assertSelectorTextContains('#assistant', 'The assistant only reads. Accepting or rejecting a proposal is your decision, and asks for your password.');
        $this->client->submitForm('Summarize');
        $this->client->followRedirect();

        $this->assertSelectorTextContains('#notes .summary', 'Tool "accept_proposal" was not found, please use one of these: get_proposal, list_speaker_proposals, find_similar_proposals, list_tracks');
        $this->assertSame(ProposalStatus::InReview, $this->findProposal('State machines with the Workflow component')->getStatus());

        // deciding stays behind the recent authentication of the reviewer
        $this->client->request('GET', '/proposals/'.$proposal->id.'/decide');
        $this->assertResponseRedirects('/login');
    }

    public function testTheToolsOfTheAssistantOnlyRead()
    {
        $this->client->loginUser($this->findUser('reviewer@example.com'));
        $this->client->enableProfiler();
        $this->client->request('GET', '/admin/proposals/'.$this->findProposal('Forms in 2027')->id.'/assistant');

        $tools = self::getAiDataCollector()->getTools();
        $this->assertSame(['get_proposal', 'list_speaker_proposals', 'find_similar_proposals', 'list_tracks'], array_map(static fn ($tool) => $tool->getName(), $tools));
        foreach ($tools as $tool) {
            $this->assertTrue($tool->isReadOnly(), $tool->getName());
        }
    }

    private function embedTheProposals(): void
    {
        // a worker embeds them in development
        static::getContainer()->get(EntityIndexer::class)->index(Proposal::class);
    }

    private function findProposal(string $title): Proposal
    {
        return $this->entityManager()->getRepository(Proposal::class)->findOneBy(['title' => $title]);
    }

    private function findUser(string $email): User
    {
        return $this->entityManager()->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
