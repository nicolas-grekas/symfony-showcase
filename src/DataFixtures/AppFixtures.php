<?php

namespace App\DataFixtures;

use App\Entity\Conference;
use App\Entity\Material\Slides;
use App\Entity\Proposal;
use App\Entity\Speaker;
use App\Entity\Ticket;
use App\Entity\User;
use App\Enum\Format;
use App\Enum\Level;
use App\Enum\ProposalStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    use ClockAwareTrait;

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $today = $this->now()->setTime(0, 0);

        $open = new Conference('SymfonyLive Lille', 'symfonylive-lille', 'Lille', $today->modify('+90 days'), $today->modify('+91 days'), $today->modify('-30 days'), $today->modify('+30 days'), ticketCapacity: 150, ticketPrice: 19900);
        $past = new Conference('SymfonyCon Amsterdam', 'symfonycon-amsterdam', 'Amsterdam', $today->modify('+20 days'), $today->modify('+22 days'), $today->modify('-120 days'), $today->modify('-60 days'), ticketCapacity: 2, ticketPrice: 34900);

        $alice = new Speaker('alice@example.com');
        $alice->name = 'Alice Martin';
        $alice->company = 'Acme';
        $alice->bio = 'Alice builds Symfony apps for a living and maintains a few open-source bundles.';
        $alice->phone = '+33 6 12 34 56 78';

        $bob = new Speaker('bob@example.com');
        $bob->name = 'Bob Durand';
        $bob->bio = 'Bob works on performance, from the database to the browser.';

        foreach ([
            [$past, $alice, 'Workflows as code', Level::Intermediate, Format::Talk, ProposalStatus::Accepted],
            [$past, $bob, 'Profiling a Symfony app end to end', Level::Advanced, Format::Workshop, ProposalStatus::Accepted],
            [$past, $bob, 'Why my cache was cold', Level::Beginner, Format::Lightning, ProposalStatus::Rejected],
            [$open, $alice, 'Forms in 2027', Level::Beginner, Format::Talk, ProposalStatus::Submitted],
        ] as [$conference, $speaker, $title, $level, $format, $status]) {
            $proposal = new Proposal($conference, $title, str_repeat($title.'. ', 6), $level, $format);
            $proposal->addSpeaker($speaker);
            $proposal->addMaterial(new Slides('https://speakerdeck.com/example/'.strtolower(str_replace(' ', '-', $title))));
            $proposal->setStatus($status);
            $proposal->markSubmitted($conference->cfpOpensAt->modify('+3 days'));
            $manager->persist($proposal);
        }

        foreach ([
            ['admin@example.com', 'Ada Admin', ['ROLE_ADMIN']],
            ['organizer@example.com', 'Olga Organizer', ['ROLE_ORGANIZER']],
            ['reviewer@example.com', 'Rémi Reviewer', ['ROLE_REVIEWER']],
        ] as [$email, $name, $roles]) {
            $user = new User($email, $name);
            $user->roles = $roles;
            $user->password = $this->passwordHasher->hashPassword($user, explode('@', $email)[0]);
            $manager->persist($user);
        }

        // speakers log in with the identity provider, so they have no password
        $manager->persist(new User('alice@example.com', 'Alice Martin'));

        // the last two seats of Amsterdam were sold this week
        foreach ([['eve@example.com', 'Eve Dubois', 2], ['frank@example.com', 'Frank Weber', 5]] as [$email, $name, $daysAgo]) {
            $manager->persist($buyer = new User($email, $name));
            $ticket = new Ticket($past, $buyer, $orderedAt = $today->modify("-$daysAgo days 10:00"));
            $ticket->markPaid('pay_fixture_'.$daysAgo, $orderedAt->modify('+2 minutes'));
            $manager->persist($ticket);
        }

        $manager->persist($open);
        $manager->persist($past);
        $manager->persist($alice);
        $manager->persist($bob);
        $manager->flush();
    }
}
