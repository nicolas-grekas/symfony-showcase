<?php

namespace App\Controller\Admin;

use App\Admin\ScheduleData;
use App\Admin\SlotData;
use App\Entity\Conference;
use App\Form\ScheduleDataType;
use App\Repository\ProposalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ORGANIZER')]
final class ScheduleController extends AbstractController
{
    #[Route('/admin/conferences/{id}/schedule', name: 'admin_schedule', requirements: ['id' => Requirement::UUID_V7], methods: ['GET', 'POST'])]
    public function edit(Conference $conference, Request $request, ProposalRepository $proposals, EntityManagerInterface $entityManager): Response
    {
        $talks = [];
        $schedule = new ScheduleData();
        foreach ($proposals->findAcceptedForConference($conference->slug) as $proposal) {
            $talks[(string) $proposal->id] = $proposal;
            $schedule->slots[(string) $proposal->id] = SlotData::fromProposal($proposal);
        }

        $form = $this->createForm(ScheduleDataType::class, $schedule)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($schedule->slots as $slot) {
                $talks[$slot->proposalId]->schedule($slot->startsAt, $slot->room);
            }
            $entityManager->flush();
            $this->addFlash('success', 'The schedule is saved.');

            return $this->redirectToRoute('admin_schedule', ['id' => $conference->id]);
        }

        return $this->render('admin/schedule.html.twig', ['conference' => $conference, 'form' => $form]);
    }
}
