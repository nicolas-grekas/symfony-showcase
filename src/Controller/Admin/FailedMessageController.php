<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/failed-messages')]
#[IsGranted('ROLE_ORGANIZER')]
final class FailedMessageController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'messenger.failure_transports.default')]
        private readonly ListableReceiverInterface $failedMessages,
        private readonly MessageBusInterface $bus,
    ) {
    }

    #[Route('', name: 'admin_failed_messages', methods: ['GET'])]
    public function index(): Response
    {
        $messages = [];
        foreach ($this->failedMessages->all(50) as $envelope) {
            $messages[] = [
                'id' => $envelope->last(TransportMessageIdStamp::class)?->getId(),
                'class' => $envelope->getMessage()::class,
                'error' => $envelope->last(ErrorDetailsStamp::class)?->getExceptionMessage(),
                'failed_at' => $envelope->last(RedeliveryStamp::class)?->getRedeliveredAt(),
            ];
        }

        return $this->render('admin/failed_messages.html.twig', ['messages' => $messages]);
    }

    #[Route('/{id}/retry', name: 'admin_failed_message_retry', methods: ['POST'])]
    #[IsCsrfTokenValid('failed-message')]
    public function retry(string $id): Response
    {
        $envelope = $this->find($id);

        // without the stamps of the failure transport, the bus routes the message as when it was first dispatched
        $this->bus->dispatch($envelope
            ->withoutStampsOfType(NonSendableStampInterface::class)
            ->withoutAll(TransportMessageIdStamp::class)
            ->withoutAll(SentToFailureTransportStamp::class));
        $this->failedMessages->ack($envelope);
        $this->addFlash('success', 'The message was sent again.');

        return $this->redirectToRoute('admin_failed_messages');
    }

    #[Route('/{id}/remove', name: 'admin_failed_message_remove', methods: ['POST'])]
    #[IsCsrfTokenValid('failed-message')]
    public function remove(string $id): Response
    {
        $this->failedMessages->reject($this->find($id));
        $this->addFlash('success', 'The message was removed.');

        return $this->redirectToRoute('admin_failed_messages');
    }

    private function find(string $id): Envelope
    {
        return $this->failedMessages->find($id) ?? throw new NotFoundHttpException(\sprintf('No failed message with id "%s".', $id));
    }
}
