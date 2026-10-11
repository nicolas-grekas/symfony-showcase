<?php

namespace App\Tests\Controller;

use App\Entity\Conference;
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

    public function testImpersonatorsSwitchBack()
    {
        $this->client->loginUser($this->findUser('admin@example.com'));
        $crawler = $this->client->request('GET', '/admin/users');
        $this->client->submit($crawler->filter('tr:contains("organizer@example.com")')->selectButton('Impersonate')->form());
        $this->client->followRedirect();

        $this->client->submitForm('Exit impersonation');
        $this->assertResponseRedirects('/admin/users');

        $this->client->followRedirect();
        $this->assertSelectorTextContains('nav', 'Ada Admin');
        $this->assertSelectorNotExists('nav button:contains("Exit impersonation")');
    }

    public function testImpersonationRequiresACsrfToken()
    {
        $this->client->loginUser($this->findUser('admin@example.com'));
        $crawler = $this->client->request('GET', '/admin/users');
        $form = $crawler->filter('tr:contains("organizer@example.com")')->selectButton('Impersonate')->form();
        $this->client->request('POST', $form->getUri(), ['_switch_user' => 'organizer@example.com']);
        $this->assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/');
        $this->assertSelectorTextContains('nav', 'Ada Admin');
    }

    public function testUsersLogOutWithAPostRequest()
    {
        $this->client->loginUser($this->findUser('organizer@example.com'));
        $this->client->request('GET', '/logout');
        $this->assertResponseStatusCodeSame(405);
        $this->client->request('POST', '/logout');
        $this->assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/');
        $this->client->submitForm('Log out');
        $this->assertResponseRedirects('/');

        $this->client->followRedirect();
        $this->assertSelectorNotExists('nav button:contains("Log out")');
        $this->assertSelectorExists('nav a:contains("Log in")');
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

    public function testReviewersSeeWhatVisitorsAreAllowedToSee()
    {
        $conference = $this->entityManager()->getRepository(Conference::class)->findOneBy(['slug' => 'symfonycon-amsterdam']);
        $this->client->loginUser($this->findUser('reviewer@example.com'));
        $this->client->request('GET', '/admin/conferences/'.$conference->id.'/proposals');

        $this->assertSelectorTextContains('tr:contains("Workflows as code") td:last-child', 'yes');
        $this->assertSelectorTextContains('tr:contains("Why my cache was cold") td:last-child', 'no');
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
