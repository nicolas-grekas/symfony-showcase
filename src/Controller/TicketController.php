<?php

namespace App\Controller;

use App\Entity\Conference;
use App\Entity\Ticket;
use App\Entity\User;
use App\Enum\TicketStatus;
use App\Payment\PaymentGateway;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\IsSignatureValid;
use Symfony\Component\HttpKernel\Attribute\Lock;
use Symfony\Component\HttpKernel\Attribute\RateLimit;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class TicketController extends AbstractController
{
    use ClockAwareTrait;

    /**
     * Purchases of the same conference wait for each other, so that the last seats are not sold twice.
     */
    #[Route('/conferences/{slug:conference}/tickets', name: 'ticket_buy', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED')]
    #[RateLimit('ticket_purchase', key: new Expression('args["user"].getUserIdentifier()'), methods: 'POST', exposeHeaders: true)]
    #[Lock('ticket-sale-{conference}', blocking: true, ttl: 10, methods: 'POST')]
    #[IsCsrfTokenValid('ticket-purchase', methods: 'POST')]
    public function buy(Conference $conference, Request $request, #[CurrentUser] User $user, TicketRepository $tickets, PaymentGateway $gateway, EntityManagerInterface $entityManager): Response
    {
        $now = $this->now();
        $ticket = $tickets->findCurrent($conference, $user, $now);
        $seatsLeft = max(0, $conference->ticketCapacity - $tickets->countTaken($conference, $now));

        if ($request->isMethod('POST') && TicketStatus::Paid !== $ticket?->status) {
            if (!$ticket && (!$seatsLeft || !$conference->isOnSale($now))) {
                $this->addFlash('error', 'No more tickets are on sale for this conference.');
            } else {
                if (!$ticket) {
                    $entityManager->persist($ticket = new Ticket($conference, $user, $now));
                    $entityManager->flush();
                }

                return $this->redirect($gateway->checkoutUrl($ticket, $this->generateUrl('ticket_buy', ['slug' => $conference->slug], UrlGeneratorInterface::ABSOLUTE_URL)), Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('ticket/buy.html.twig', [
            'conference' => $conference,
            'ticket' => $ticket,
            'seats_left' => $seatsLeft,
            'on_sale' => $conference->isOnSale($now),
        ], new Response(status: $request->isMethod('POST') ? Response::HTTP_CONFLICT : Response::HTTP_OK));
    }

    #[Route('/tickets/{id}', name: 'ticket_show', requirements: ['id' => Requirement::UUID_V7], methods: ['GET'])]
    #[IsSignatureValid]
    public function show(Ticket $ticket): Response
    {
        if (TicketStatus::Paid !== $ticket->status) {
            throw $this->createNotFoundException();
        }

        return $this->render('ticket/show.html.twig', ['ticket' => $ticket]);
    }

    #[Route('/me/tickets', name: 'me_tickets', methods: ['GET'])]
    public function mine(#[CurrentUser] User $user, TicketRepository $tickets): Response
    {
        return $this->render('ticket/mine.html.twig', ['tickets' => $tickets->findPaidByBuyer($user)]);
    }
}
