<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Conference;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ConferenceControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->loginUser($this->entityManager()->getRepository(User::class)->findOneBy(['email' => 'organizer@example.com']));
    }

    public function testCreateAConference()
    {
        $this->client->request('GET', '/admin/conferences/new');
        $this->client->submitForm('Save', [
            'conference_data[name]' => 'SymfonyLive Paris',
            'conference_data[slug]' => 'symfonylive-paris',
            'conference_data[city]' => 'Paris',
            'conference_data[dates][from]' => '2027-03-26',
            'conference_data[dates][to]' => '2027-03-27',
            'conference_data[cfp][from]' => '2026-10-01T09:00',
            'conference_data[cfp][to]' => '2026-12-01T18:00',
            'conference_data[ticketCapacity]' => '400',
            'conference_data[ticketPrice]' => '249.00',
        ]);

        $this->assertResponseRedirects('/admin');
        $conference = $this->entityManager()->getRepository(Conference::class)->findOneBy(['slug' => 'symfonylive-paris']);
        $this->assertSame('2027-03-26', $conference->startsOn->format('Y-m-d'));
        $this->assertSame('2026-12-01 18:00', $conference->cfpClosesAt->format('Y-m-d H:i'));
        $this->assertSame(24900, $conference->ticketPrice);
    }

    public function testTheBoundsOfARangeAreOrdered()
    {
        $this->client->request('GET', '/admin/conferences/new');
        $this->client->submitForm('Save', [
            'conference_data[name]' => 'SymfonyLive Paris',
            'conference_data[slug]' => 'symfonycon-amsterdam',
            'conference_data[city]' => 'Paris',
            'conference_data[dates][from]' => '2027-03-27',
            'conference_data[dates][to]' => '2027-03-26',
            'conference_data[cfp][from]' => '2026-10-01T09:00',
            'conference_data[cfp][to]' => '2026-12-01T18:00',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('main', 'The lower bound must not be greater than the upper bound.');
        $this->assertSelectorTextContains('main', 'This value is already used.');
    }

    public function testEditAConference()
    {
        $conference = $this->entityManager()->getRepository(Conference::class)->findOneBy(['slug' => 'symfonylive-lille']);
        $this->client->request('GET', '/admin/conferences/'.$conference->id.'/edit');
        $this->assertInputValueSame('conference_data[dates][from]', $conference->startsOn->format('Y-m-d'));

        $this->client->submitForm('Save', ['conference_data[city]' => 'Lille Grand Palais', 'conference_data[slug]' => 'symfonylive-lille']);

        $this->assertResponseRedirects('/admin');
        $this->entityManager()->clear();
        $this->assertSame('Lille Grand Palais', $this->entityManager()->find(Conference::class, $conference->id)->city);
    }

    public function testReviewersCannotEditConferences()
    {
        $this->client->loginUser($this->entityManager()->getRepository(User::class)->findOneBy(['email' => 'reviewer@example.com']));
        $this->client->request('GET', '/admin/conferences/new');

        $this->assertResponseStatusCodeSame(403);
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
