<?php

namespace App\DataFixtures;

use App\Entity\Conference;
use App\Entity\Material\Slides;
use App\Entity\Proposal;
use App\Entity\Speaker;
use App\Enum\Format;
use App\Enum\Level;
use App\Enum\ProposalStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Clock\ClockAwareTrait;

class AppFixtures extends Fixture
{
    use ClockAwareTrait;

    public function load(ObjectManager $manager): void
    {
        $today = $this->now()->setTime(0, 0);

        $open = new Conference('SymfonyLive Lille', 'symfonylive-lille', 'Lille', $today->modify('+90 days'), $today->modify('+91 days'), $today->modify('-30 days'), $today->modify('+30 days'));
        $past = new Conference('SymfonyCon Amsterdam', 'symfonycon-amsterdam', 'Amsterdam', $today->modify('+20 days'), $today->modify('+22 days'), $today->modify('-120 days'), $today->modify('-60 days'));

        $alice = new Speaker('alice@example.com');
        $alice->name = 'Alice Martin';
        $alice->company = 'Acme';
        $alice->bio = 'Alice builds Symfony apps for a living and maintains a few open-source bundles.';

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

        $manager->persist($open);
        $manager->persist($past);
        $manager->persist($alice);
        $manager->persist($bob);
        $manager->flush();
    }
}
