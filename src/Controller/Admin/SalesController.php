<?php

namespace App\Controller\Admin;

use App\Entity\Conference;
use App\Repository\TicketRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ORGANIZER')]
final class SalesController extends AbstractController
{
    use ClockAwareTrait;

    #[Route('/admin/conferences/{id}/sales', name: 'admin_sales', requirements: ['id' => Requirement::UUID_V7])]
    public function sales(Conference $conference, TicketRepository $tickets): Response
    {
        $today = $this->now()->setTime(0, 0);
        $days = [];
        for ($i = 6; 0 <= $i; --$i) {
            $days[$today->modify("-$i days")->format('Y-m-d')] = 0;
        }

        // the id of a ticket tells when it was ordered
        foreach ($tickets->findPaid($conference, since: $today->modify('-6 days')) as $ticket) {
            ++$days[$ticket->id->getDateTime()->setTimezone($today->getTimezone())->format('Y-m-d')];
        }

        return $this->render('admin/sales.html.twig', [
            'conference' => $conference,
            'totals' => $tickets->sumPaid($conference),
            'days' => $days,
        ]);
    }
}
