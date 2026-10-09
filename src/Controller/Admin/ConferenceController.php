<?php

namespace App\Controller\Admin;

use App\Admin\ConferenceData;
use App\Entity\Conference;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/conferences')]
#[IsGranted('ROLE_ORGANIZER')]
final class ConferenceController extends AbstractController
{
    #[Route('/new', name: 'admin_conference_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ConferenceData::class, new ConferenceData())->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $entityManager->persist(new Conference($data->name, $data->slug, $data->city, $data->dates->from, $data->dates->to, $data->cfp->from, $data->cfp->to, $data->ticketCapacity, $data->ticketPrice));
            $entityManager->flush();
            $this->addFlash('success', \sprintf('"%s" is created.', $data->name));

            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->render('admin/conference_form.html.twig', ['form' => $form, 'title' => 'New conference']);
    }

    #[Route('/{id}/edit', name: 'admin_conference_edit', requirements: ['id' => Requirement::UUID_V7], methods: ['GET', 'POST'])]
    public function edit(Conference $conference, Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ConferenceData::class, ConferenceData::fromConference($conference))->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $conference->update($data->name, $data->slug, $data->city, $data->dates->from, $data->dates->to, $data->cfp->from, $data->cfp->to, $data->ticketCapacity, $data->ticketPrice);
            $entityManager->flush();
            $this->addFlash('success', \sprintf('"%s" is updated.', $data->name));

            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->render('admin/conference_form.html.twig', ['form' => $form, 'title' => $conference->name]);
    }
}
