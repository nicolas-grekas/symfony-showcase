<?php

namespace App\Tests\Assistant;

use App\Entity\Proposal;
use App\Entity\User;
use App\Twig\Components\SemanticSearch;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\AI\Store\Bridge\Doctrine\EntityIndexer;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class SemanticSearchTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testReviewersSearchTheTalksAndProposalsByMeaning()
    {
        static::getContainer()->get(EntityIndexer::class)->index(Proposal::class);
        $search = $this->createLiveComponent(SemanticSearch::class)->actingAs($this->findUser('reviewer@example.com'));
        $this->assertCount(0, $search->render()->crawler()->filter('tbody tr'));

        $search->set('query', 'profiling a slow Symfony app');

        $rows = $search->render()->crawler()->filter('tbody tr');
        $this->assertCount(5, $rows);
        $this->assertSame('Finding the slow parts of a Symfony app', $rows->eq(0)->filter('td')->eq(0)->text());
        $this->assertSame('SymfonyLive Lille', $rows->eq(0)->filter('td')->eq(1)->text());
        $this->assertSame('Submitted', $rows->eq(0)->filter('td')->eq(2)->text());
        // the talk that it repeats
        $this->assertSame('Profiling a Symfony app end to end', $rows->eq(1)->filter('td')->eq(0)->text());
        $this->assertSame('Accepted', $rows->eq(1)->filter('td')->eq(2)->text());
        // the closest first, with a bar of their similarity
        $similarities = $rows->filter('meter')->each(static fn ($meter) => (float) $meter->attr('value'));
        $sorted = $similarities;
        rsort($sorted);
        $this->assertSame($sorted, $similarities);
        $this->assertGreaterThan($similarities[4], $similarities[0]);
        $this->assertSame(round($similarities[0] * 100).'%', $rows->eq(0)->filter('td')->eq(3)->text());
    }

    public function testOnlyReviewersSearch()
    {
        $this->expectException(AccessDeniedException::class);

        $this->createLiveComponent(SemanticSearch::class)->actingAs($this->findUser('alice@example.com'))->render();
    }

    public function testTheBackOfficeHasTheSearch()
    {
        $client = static::createClient();
        $client->loginUser($this->findUser('reviewer@example.com'));

        $client->request('GET', '/admin');

        $this->assertSelectorExists('[data-controller="live"] input[type="search"][data-model="debounce(300)|query"]');
    }

    private function findUser(string $email): User
    {
        return static::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['email' => $email]);
    }
}
