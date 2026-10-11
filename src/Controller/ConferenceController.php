<?php

namespace App\Controller;

use App\Entity\Conference;
use App\Entity\Proposal;
use App\Repository\ConferenceRepository;
use App\Repository\ProposalRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ConferenceController extends AbstractController
{
    use ClockAwareTrait;

    #[Route('/', name: 'home')]
    public function index(ConferenceRepository $conferences): Response
    {
        return $this->render('conference/index.html.twig', [
            'conferences' => $conferences->findUpcoming($this->now()),
            'now' => $this->now(),
        ]);
    }

    /**
     * @param list<Proposal> $talks
     */
    #[Route('/conferences/{slug:conference}', name: 'conference_show')]
    public function show(Conference $conference, #[MapEntity(class: Proposal::class, expr: 'repository.findAcceptedForConference(conference)')] array $talks): Response
    {
        return $this->render('conference/show.html.twig', [
            'conference' => $conference,
            'talks' => $talks,
            'now' => $this->now(),
        ]);
    }

    #[Route('/conferences/{slug:conference}/schedule', name: 'conference_schedule', methods: ['GET'])]
    public function schedule(Conference $conference, ProposalRepository $proposals): Response
    {
        $days = [];
        foreach ($proposals->findScheduled($conference) as $talk) {
            $days[$talk->scheduledAt->format('Y-m-d')][$talk->scheduledAt->format('H:i')][] = $talk;
        }

        return $this->render('conference/schedule.html.twig', ['conference' => $conference, 'days' => $days]);
    }
}
