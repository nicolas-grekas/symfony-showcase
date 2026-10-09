<?php

namespace App\Cfp;

use App\Entity\Conference;
use App\Entity\Material\Slides;
use App\Entity\Material\Video;
use App\Entity\Proposal;
use App\Entity\Speaker;
use App\Entity\User;
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

    public function submit(Conference $conference, ProposalDraft $draft, User $user): Proposal
    {
        $speaker = $this->speakers->findOneBy(['email' => $user->email]) ?? new Speaker($user->email);
        $speaker->name = $draft->speaker->name;
        $speaker->company = $draft->speaker->company;
        $speaker->bio = $draft->speaker->bio;
        $speaker->phone = $draft->speaker->phone ?? $speaker->phone;

        $proposal = new Proposal($conference, $draft->talk->title, $draft->talk->abstract, $draft->talk->level, $draft->talk->format);
        $proposal->addSpeaker($speaker);

        foreach ($draft->materials as $material) {
            $proposal->addMaterial($material instanceof VideoData ? new Video($material->url, $material->minutes) : new Slides($material->url));
        }

        // the ProposalSubmitted event goes to the outbox in the same transaction as the proposal
        $this->entityManager->wrapInTransaction(function () use ($speaker, $proposal) {
            $this->workflow->apply($proposal, ProposalWorkflow::SUBMIT);
            $this->entityManager->persist($speaker);
            $this->entityManager->persist($proposal);
        });

        return $proposal;
    }
}
