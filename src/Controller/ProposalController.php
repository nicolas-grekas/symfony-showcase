<?php

namespace App\Controller;

use App\Entity\Proposal;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class ProposalController extends AbstractController
{
    #[Route('/proposals/{id}', name: 'proposal_show', requirements: ['id' => Requirement::UUID_V7])]
    public function show(Proposal $proposal): Response
    {
        return $this->render('proposal/show.html.twig', ['proposal' => $proposal]);
    }

    /**
     * @param list<Proposal> $proposals
     */
    #[Route('/conferences/{conference}/proposals', name: 'proposal_index', requirements: ['conference' => Requirement::UUID_V7])]
    public function index(#[MapEntity(class: Proposal::class, mapping: ['conference' => 'conference'])] array $proposals): Response
    {
        return $this->render('proposal/index.html.twig', ['proposals' => $proposals]);
    }
}
