<?php

namespace App\Tests\Assistant;

use App\Assistant\FakePlatform;
use App\Cfp\ProposalDraft;
use App\Cfp\ProposalSubmitter;
use App\Entity\Conference;
use App\Entity\Proposal;
use App\Entity\User;
use App\Enum\Format;
use App\Enum\Level;
use App\Enum\ProposalStatus;
use App\Message\EmbedProposal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class IndexingTest extends KernelTestCase
{
    public function testSubmittedProposalsAreEmbeddedByAWorker()
    {
        self::bootKernel();
        $draft = new ProposalDraft();
        $draft->speaker->name = 'Alice Martin';
        $draft->speaker->bio = 'Alice builds Symfony apps.';
        $draft->talk->title = 'Embeddings in Postgres';
        $draft->talk->abstract = 'How pgvector stores the embeddings of an app next to its data.';
        $draft->talk->level = Level::Intermediate;
        $draft->talk->format = Format::Talk;

        $proposal = static::getContainer()->get(ProposalSubmitter::class)->submit($this->findConference('symfonylive-lille'), $draft, $this->findUser('alice@example.com'));

        // the model is not called while the speaker waits
        $this->assertNull($proposal->embedding);
        $this->assertQueuedMessageCount(1, 'async', EmbedProposal::class);

        static::consumeQueuedMessages('async');

        // pgvector stores single-precision floats
        $this->assertEqualsWithDelta(
            FakePlatform::embed("Embeddings in Postgres\nHow pgvector stores the embeddings of an app next to its data.")->getData(),
            $this->findProposal('Embeddings in Postgres')->embedding->getData(),
            1e-6,
        );
    }

    public function testProposalsAreEmbeddedAgainWhenTheirTextChanges()
    {
        self::bootKernel();
        $proposal = $this->findProposal('Forms in 2027');

        $proposal->setStatus(ProposalStatus::InReview);
        $this->entityManager()->flush();
        $this->assertQueuedMessageCount(0, 'async', EmbedProposal::class);

        $proposal->abstract = 'Forms, again.';
        $this->entityManager()->flush();
        $this->assertQueuedMessageCount(1, 'async', EmbedProposal::class);
    }

    public function testACommandEmbedsAllProposals()
    {
        self::bootKernel();
        $tester = new CommandTester((new Application(self::$kernel))->find('assistant:index'));

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $count = $this->entityManager()->getRepository(Proposal::class)->count();
        $this->assertStringContainsString(\sprintf('%d proposals are embedded.', $count), $tester->getDisplay());
        $this->assertSame($count, (int) $this->entityManager()->createQuery('SELECT COUNT(p) FROM App\Entity\Proposal p WHERE p.embedding IS NOT NULL')->getSingleScalarResult());
    }

    public function testTheCommandCanEmbedOnlyTheMissingProposals()
    {
        self::bootKernel();
        (new CommandTester((new Application(self::$kernel))->find('assistant:index')))->execute([]);
        $this->entityManager()->createQuery('UPDATE App\Entity\Proposal p SET p.embedding = NULL WHERE p.title = :title')->execute(['title' => 'Forms in 2027']);

        $tester = new CommandTester((new Application(self::$kernel))->find('assistant:index'));
        $tester->execute(['--missing' => true]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('1 proposal is embedded.', $tester->getDisplay());
    }

    private function findProposal(string $title): Proposal
    {
        return $this->entityManager()->getRepository(Proposal::class)->findOneBy(['title' => $title]);
    }

    private function findConference(string $slug): Conference
    {
        return $this->entityManager()->getRepository(Conference::class)->findOneBy(['slug' => $slug]);
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
