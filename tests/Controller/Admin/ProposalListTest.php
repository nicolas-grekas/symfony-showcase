<?php

namespace App\Tests\Controller\Admin;

use App\Cfp\ProposalDraft;
use App\Cfp\ProposalSubmitter;
use App\Entity\Conference;
use App\Entity\User;
use App\Enum\Format;
use App\Enum\Level;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class ProposalListTest extends WebTestCase
{
    public function testTheListListensToTheProposalsOfItsConference()
    {
        $client = static::createClient();
        $client->loginUser($this->findUser('reviewer@example.com'));
        $path = '/admin/conferences/'.$this->findConference('symfonylive-lille')->id.'/proposals';

        $client->request('GET', $path);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('meta[name="turbo-refresh-method"][content="morph"]');
        $this->assertStringEndsWith('?match='.rawurlencode($path), $client->getCrawler()->filter('turbo-mercure-stream-source')->attr('src'));
        $this->assertSelectorNotExists('turbo-mercure-stream-source[private]');
        $this->assertSelectorExists('tbody[data-controller="arrivals"] tr[data-arrivals-target="row"][id^="proposal-"]');
    }

    public function testTheListsRefreshWhenAProposalIsSubmitted()
    {
        self::bootKernel();
        $conference = $this->findConference('symfonylive-lille');
        $draft = new ProposalDraft();
        $draft->speaker->name = 'Alice Martin';
        $draft->speaker->bio = 'Alice builds Symfony apps.';
        $draft->talk->title = 'Live updates with Mercure';
        $draft->talk->abstract = 'Turbo Streams pushed to the browser by a Mercure hub.';
        $draft->talk->level = Level::Intermediate;
        $draft->talk->format = Format::Talk;

        static::getContainer()->get(ProposalSubmitter::class)->submit($conference, $draft, $this->findUser('alice@example.com'));
        static::consumeQueuedMessages('events.outbox');
        static::consumeQueuedMessages('events');

        /** @var list<Update> $updates */
        $updates = array_column(static::getContainer()->get(HubInterface::class)->getMessages(), 'object');
        $this->assertCount(1, $updates);
        $this->assertSame(['/admin/conferences/'.$conference->id.'/proposals'], $updates[0]->getTopics());
        // the pages load the list again with the session of their reviewer: the hub never sees a proposal
        $this->assertSame('<turbo-stream action="refresh"></turbo-stream>', $updates[0]->getData());
        $this->assertFalse($updates[0]->isPrivate());
    }

    private function findConference(string $slug): Conference
    {
        return static::getContainer()->get(EntityManagerInterface::class)->getRepository(Conference::class)->findOneBy(['slug' => $slug]);
    }

    private function findUser(string $email): User
    {
        return static::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['email' => $email]);
    }
}
