<?php

namespace App\Controller;

use App\Entity\Proposal;
use App\Security\ProposalVoter;
use App\Workflow\ProposalWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/proposals/{id}', requirements: ['id' => Requirement::UUID_V7])]
final class ProposalController extends AbstractController
{
    #[Route('', name: 'proposal_show', methods: ['GET'])]
    #[IsGranted(ProposalVoter::VIEW, 'proposal', statusCode: 404)]
    public function show(Proposal $proposal, ProposalWorkflow $workflow): Response
    {
        return $this->render('proposal/show.html.twig', [
            'proposal' => $proposal,
            'transitions' => $workflow->getEnabledTransitions($proposal),
        ]);
    }

    #[Route('/withdraw', name: 'proposal_withdraw', methods: ['POST'])]
    #[IsGranted(ProposalVoter::WITHDRAW, 'proposal')]
    #[IsCsrfTokenValid('proposal-transition')]
    public function withdraw(Proposal $proposal, ProposalWorkflow $workflow): Response
    {
        return $this->applyTransition($proposal, $workflow, ProposalWorkflow::WITHDRAW);
    }

    #[Route('/review', name: 'proposal_start_review', methods: ['POST'])]
    #[IsGranted(ProposalVoter::REVIEW, 'proposal')]
    #[IsCsrfTokenValid('proposal-transition')]
    public function startReview(Proposal $proposal, ProposalWorkflow $workflow): Response
    {
        return $this->applyTransition($proposal, $workflow, ProposalWorkflow::START_REVIEW);
    }

    #[Route('/accept', name: 'proposal_accept', methods: ['POST'])]
    #[IsGranted(ProposalVoter::DECIDE, 'proposal')]
    #[IsCsrfTokenValid('proposal-transition')]
    public function accept(Proposal $proposal, ProposalWorkflow $workflow): Response
    {
        return $this->applyTransition($proposal, $workflow, ProposalWorkflow::ACCEPT);
    }

    #[Route('/reject', name: 'proposal_reject', methods: ['POST'])]
    #[IsGranted(ProposalVoter::DECIDE, 'proposal')]
    #[IsCsrfTokenValid('proposal-transition')]
    public function reject(Proposal $proposal, ProposalWorkflow $workflow): Response
    {
        return $this->applyTransition($proposal, $workflow, ProposalWorkflow::REJECT);
    }

    private function applyTransition(Proposal $proposal, ProposalWorkflow $workflow, string $transition): Response
    {
        if (!$workflow->can($proposal, $transition)) {
            $this->addFlash('error', \sprintf('The proposal cannot be %s now.', $transition));
        } else {
            $workflow->apply($proposal, $transition);
            $this->container->get('doctrine')->getManager()->flush();
        }

        return $this->redirectToRoute('proposal_show', ['id' => $proposal->id]);
    }
}
