<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ProposalRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class SpeakerController extends AbstractController
{
    #[Route('/me/proposals', name: 'speaker_proposals')]
    public function proposals(#[CurrentUser] User $user, ProposalRepository $proposals): Response
    {
        return $this->render('speaker/proposals.html.twig', ['proposals' => $proposals->findBySpeakerEmail($user->email)]);
    }
}
