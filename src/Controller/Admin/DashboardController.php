<?php

namespace App\Controller\Admin;

use App\Entity\Conference;
use App\Entity\Proposal;
use App\Repository\ConferenceRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_REVIEWER')]
final class DashboardController extends AbstractController
{
    #[Route('', name: 'admin_dashboard')]
    public function index(ConferenceRepository $conferences): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'conferences' => $conferences->findBy([], ['startsOn' => 'DESC']),
        ]);
    }

    /**
     * @param list<Proposal> $proposals
     */
    #[Route('/conferences/{conference}/proposals', name: 'admin_proposals', requirements: ['conference' => Requirement::UUID_V7])]
    public function proposals(Conference $conference, #[MapEntity(class: Proposal::class, mapping: ['conference' => 'conference'])] array $proposals): Response
    {
        return $this->render('admin/proposals.html.twig', ['conference' => $conference, 'proposals' => $proposals]);
    }
}
