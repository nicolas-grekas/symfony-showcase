<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Conference;
use App\Entity\Proposal;
use App\Entity\User;
use App\Enum\Room;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ScheduleControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->loginUser($this->entityManager()->getRepository(User::class)->findOneBy(['email' => 'organizer@example.com']));
    }

    public function testScheduleTheAcceptedTalks()
    {
        $conference = $this->conference();
        $workflows = $this->proposal('Workflows as code');
        $profiling = $this->proposal('Profiling a Symfony app end to end');
        $day = $conference->startsOn->format('Y-m-d');

        $this->client->request('GET', '/admin/conferences/'.$conference->id.'/schedule');
        $this->assertResponseIsSuccessful();

        // the entries are named after the proposals, not after their position
        $this->client->submitForm('Save', [
            "schedule_data[slots][{$workflows->id}][startsAt]" => "{$day}T09:30",
            "schedule_data[slots][{$workflows->id}][room]" => 'main',
            "schedule_data[slots][{$profiling->id}][startsAt]" => "{$day}T09:30",
            "schedule_data[slots][{$profiling->id}][room]" => 'workshop',
        ]);
        $this->assertResponseRedirects();

        $this->entityManager()->clear();
        $workflows = $this->proposal('Workflows as code');
        $this->assertSame(Room::Main, $workflows->room);
        $this->assertSame("$day 09:30", $workflows->scheduledAt->format('Y-m-d H:i'));

        $this->client->request('GET', '/conferences/symfonycon-amsterdam/schedule');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.slot', '09:30');
        $this->assertSelectorCount(2, '.slot .talk');
    }

    public function testTwoTalksCannotShareARoomAtTheSameTime()
    {
        $conference = $this->conference();
        $day = $conference->startsOn->format('Y-m-d');
        $ids = [$this->proposal('Workflows as code')->id, $this->proposal('Profiling a Symfony app end to end')->id];

        $this->client->request('GET', '/admin/conferences/'.$conference->id.'/schedule');
        $this->client->submitForm('Save', [
            "schedule_data[slots][{$ids[0]}][startsAt]" => "{$day}T14:00",
            "schedule_data[slots][{$ids[0]}][room]" => 'main',
            "schedule_data[slots][{$ids[1]}][startsAt]" => "{$day}T14:00",
            "schedule_data[slots][{$ids[1]}][room]" => 'main',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('main', 'Two talks are in the same room at the same time.');
    }

    private function conference(): Conference
    {
        return $this->entityManager()->getRepository(Conference::class)->findOneBy(['slug' => 'symfonycon-amsterdam']);
    }

    private function proposal(string $title): Proposal
    {
        return $this->entityManager()->getRepository(Proposal::class)->findOneBy(['title' => $title]);
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
