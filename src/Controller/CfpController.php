<?php

namespace App\Controller;

use App\Cfp\ProposalDraft;
use App\Cfp\ProposalSubmitter;
use App\Entity\Conference;
use App\Entity\User;
use App\Form\ProposalFlowType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CfpController extends AbstractController
{
    use ClockAwareTrait;

    #[Route('/conferences/{slug:conference}/cfp', name: 'cfp_submit')]
    #[IsGranted('IS_AUTHENTICATED')]
    public function submit(Conference $conference, Request $request, ProposalSubmitter $submitter, #[CurrentUser] User $user): Response
    {
        if (!$conference->isCfpOpen($this->now())) {
            return $this->render('cfp/closed.html.twig', ['conference' => $conference], new Response(status: Response::HTTP_GONE));
        }

        $draft = new ProposalDraft();
        $draft->speaker->name = $user->name;
        $flow = $this->createForm(ProposalFlowType::class, $draft)->handleRequest($request);

        if ($flow->isSubmitted() && $flow->isValid() && $flow->isFinished()) {
            $proposal = $submitter->submit($conference, $flow->getData(), $user);
            $this->addFlash('success', 'Thank you, your proposal has been submitted!');

            return $this->redirectToRoute('proposal_show', ['id' => $proposal->id]);
        }

        return $this->render('cfp/submit.html.twig', [
            'conference' => $conference,
            'form' => $flow->getStepForm(),
        ]);
    }
}
