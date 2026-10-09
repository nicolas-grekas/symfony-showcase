<?php

namespace App\Cfp;

use App\Entity\Conference;
use App\Entity\Material\Slides;
use App\Entity\Material\Video;
use App\Entity\Proposal;
use App\Entity\Speaker;
use App\Repository\SpeakerRepository;
use App\Workflow\ProposalWorkflow;
use Doctrine\ORM\EntityManagerInterface;

final class ProposalSubmitter
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SpeakerRepository $speakers,
        private readonly ProposalWorkflow $workflow,
    ) {
    }

    public function submit(Conference $conference, ProposalDraft $draft): Proposal
    {
        $speaker = $this->speakers->findOneBy(['email' => $draft->speaker->email]) ?? new Speaker($draft->speaker->email);
        $speaker->name = $draft->speaker->name;
        $speaker->company = $draft->speaker->company;
        $speaker->bio = $draft->speaker->bio;

        $proposal = new Proposal($conference, $draft->talk->title, $draft->talk->abstract, $draft->talk->level, $draft->talk->format);
        $proposal->addSpeaker($speaker);

        foreach ($draft->materials as $material) {
            $proposal->addMaterial($material instanceof VideoData ? new Video($material->url, $material->minutes) : new Slides($material->url));
        }

        $this->workflow->apply($proposal, ProposalWorkflow::SUBMIT);

        $this->entityManager->persist($speaker);
        $this->entityManager->persist($proposal);
        $this->entityManager->flush();

        return $proposal;
    }
}
