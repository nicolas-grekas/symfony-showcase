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

        $chloe = new Speaker('chloe@example.com');
        $chloe->name = 'Chloé Bernard';
        $chloe->company = 'Globex';
        $chloe->bio = 'Chloé leads a team that maintains a large Symfony app.';

        $hugo = new Speaker('hugo@example.com');
        $hugo->name = 'Hugo Moreau';
        $hugo->bio = 'Hugo connects Symfony apps to AI clients.';

        // the proposals to Lille repeat some talks of Amsterdam, for the similarity search of the review assistant
        foreach ([
            [$past, $alice, 'Workflows as code', Level::Intermediate, Format::Talk, ProposalStatus::Accepted, 'The Workflow component models the life of an object as places and transitions. This talk declares a workflow with PHP attributes, guards its transitions with listeners and draws it as a diagram. We follow a proposal from its submission to its acceptance.'],
            [$past, $bob, 'Profiling a Symfony app end to end', Level::Advanced, Format::Workshop, ProposalStatus::Accepted, 'A slow page has many possible causes: the database, the cache, the templates or the browser. In this workshop, we profile a Symfony app with the profiler, Blackfire and the tools of the browser, and we fix what they find. Bring a laptop with your own app.'],
            [$past, $bob, 'Why my cache was cold', Level::Beginner, Format::Lightning, ProposalStatus::Rejected, 'After each deployment, the first requests of our app took seconds. This lightning talk tells how we found that the cache was cleared on every deployment, and how we warm it up now.'],
            [$open, $alice, 'Forms in 2027', Level::Beginner, Format::Talk, ProposalStatus::Submitted, 'Symfony forms got data classes, multi-step flows and polymorphic collections. This talk builds a call for papers with them, and shows what each feature replaces in older code.'],
            [$open, $chloe, 'Finding the slow parts of a Symfony app', Level::Intermediate, Format::Talk, ProposalStatus::Submitted, 'When a page is slow, the cause can be the database, the cache, the templates or the browser. This talk profiles a Symfony app with the profiler and Blackfire, then fixes the slow queries and templates they find.'],
            [$open, $chloe, 'State machines with the Workflow component', Level::Intermediate, Format::Talk, ProposalStatus::InReview, 'A state machine tells which transitions an object can take from each place. This talk models the review of a proposal as a state machine with the Workflow component, with PHP attributes, guard listeners and a diagram.'],
            [$open, $bob, 'Warming up the cache before the first request', Level::Beginner, Format::Lightning, ProposalStatus::Submitted, 'Our first requests after a deployment were slow because the cache was cold. This lightning talk shows how to warm up the cache during the deployment, before the app gets any traffic.'],
            [$open, $hugo, 'An MCP server for your Symfony app', Level::Advanced, Format::Talk, ProposalStatus::Submitted, 'The MCP bundle of Symfony AI exposes the services of an app as tools for AI clients. This talk builds an MCP server behind the firewall of the app, with scopes and the user of each request.'],
        ] as [$conference, $speaker, $title, $level, $format, $status, $abstract]) {
            $proposal = new Proposal($conference, $title, $abstract, $level, $format);
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
        $manager->persist($chloe);
        $manager->persist($hugo);
        $manager->flush();
    }
}
