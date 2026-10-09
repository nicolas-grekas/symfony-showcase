<?php

namespace App\Tests\Controller;

use App\Entity\Proposal;
use App\Entity\User;
use App\Enum\ProposalStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testVisitorsOnlySeeAcceptedProposals()
    {
        $this->client->request('GET', '/proposals/'.$this->findProposal('Workflows as code')->id);
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/proposals/'.$this->findProposal('Why my cache was cold')->id);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testReviewersConfirmTheirPasswordBeforeDeciding()
    {
        $proposal = $this->findProposal('Forms in 2027');
        $proposal->setStatus(ProposalStatus::InReview);
        $this->entityManager()->flush();

        // a token restored from the session carries no proof of a recent authentication
        $this->client->loginUser($this->findUser('reviewer@example.com'));
        $this->client->request('GET', '/proposals/'.$proposal->id.'/decide');
        $this->assertResponseRedirects('/login');

        $this->client->followRedirect();
        $this->assertSelectorTextContains('h1', 'Confirm your password');
        $this->assertInputValueSame('_username', 'reviewer@example.com');

        $this->client->submitForm('Log in', ['_password' => 'reviewer']);
        $this->assertResponseRedirects('http://localhost/proposals/'.$proposal->id.'/decide');

        $this->client->followRedirect();
        $this->client->submitForm('Accept');
        $this->assertResponseRedirects();
        $this->assertSame(ProposalStatus::Accepted, $this->findProposal('Forms in 2027')->getStatus());
    }

    public function testSpeakersWithdrawTheirProposals()
    {
        $proposal = $this->findProposal('Forms in 2027');
        $this->client->loginUser($this->findUser('alice@example.com'));
        $this->client->request('GET', '/proposals/'.$proposal->id);
        $this->client->submitForm('Withdraw');

        $this->assertResponseRedirects();
        $this->assertSame(ProposalStatus::Withdrawn, $this->findProposal('Forms in 2027')->getStatus());
    }

    public function testAdminsImpersonateUsers()
    {
        $this->client->loginUser($this->findUser('admin@example.com'));
        $crawler = $this->client->request('GET', '/admin/users');
        $form = $crawler->filter('tr:contains("organizer@example.com")')->selectButton('Impersonate')->form();
        $this->client->submit($form);
        $this->client->followRedirect();

        $this->assertSelectorTextContains('nav', 'Olga Organizer');
        $this->assertSelectorExists('nav button:contains("Exit impersonation")');
    }

    public function testDisabledUsersAreLoggedOut()
    {
        $this->client->loginUser($this->findUser('organizer@example.com'));
        $this->client->request('GET', '/admin');
        $this->assertResponseIsSuccessful();

        $this->findUser('organizer@example.com')->enabled = false;
        $this->entityManager()->flush();

        $this->client->request('GET', '/admin');
        $this->assertResponseRedirects('/login');
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
