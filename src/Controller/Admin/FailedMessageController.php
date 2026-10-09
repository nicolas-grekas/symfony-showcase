<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Failure\FailedMessageRepository;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/failed-messages')]
#[IsGranted('ROLE_ORGANIZER')]
final class FailedMessageController extends AbstractController
{
    public function __construct(
        private readonly FailedMessageRepository $failedMessages,
    ) {
    }

    #[Route('', name: 'admin_failed_messages', methods: ['GET'])]
    public function index(): Response
    {
        $messages = [];
        foreach ($this->failedMessages->all(null, null, 50) as $envelope) {
            $messages[] = [
                'id' => FailedMessageRepository::getMessageId($envelope),
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
        $this->failedMessages->redispatch($this->find($id));
        $this->addFlash('success', 'The message was sent again.');

        return $this->redirectToRoute('admin_failed_messages');
    }

    #[Route('/{id}/remove', name: 'admin_failed_message_remove', methods: ['POST'])]
    #[IsCsrfTokenValid('failed-message')]
    public function remove(string $id): Response
    {
        $this->failedMessages->remove($this->find($id));
        $this->addFlash('success', 'The message was removed.');

        return $this->redirectToRoute('admin_failed_messages');
    }

    private function find(string $id): Envelope
    {
        return $this->failedMessages->find($id) ?? throw new NotFoundHttpException(\sprintf('No failed message with id "%s".', $id));
    }
}
