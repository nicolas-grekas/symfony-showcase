<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Webhook\Messenger\SendWebhookMessage;
use Symfony\Component\Webhook\Subscriber;

/**
 * Stands in for the checkout page of the payment provider in development.
 *
 * It reports payments the way the provider does, with webhooks signed according to Standard Webhooks.
 */
final class FakePaymentProviderController extends AbstractController
{
    #[Route('/_fake/payments/checkout', name: 'fake_payment_checkout', methods: ['GET', 'POST'], env: 'dev')]
    public function checkout(
        Request $request,
        MessageBusInterface $bus,
        #[Autowire(env: 'PAYMENTS_WEBHOOK_SECRET')] string $secret,
        #[MapQueryParameter] string $reference,
        #[MapQueryParameter] int $amount,
        #[MapQueryParameter] string $description,
        #[MapQueryParameter(name: 'return_url')] string $returnUrl,
    ): Response {
        if (!$request->isMethod('POST')) {
            return $this->render('fake_payment/checkout.html.twig', ['amount' => $amount, 'description' => $description]);
        }

        $event = $request->getPayload()->has('pay') ? 'payment.succeeded' : 'payment.canceled';
        $paymentId = 'pay_'.bin2hex(random_bytes(8));

        // sent by a worker, as the provider would, once the buyer is back on the site
        $bus->dispatch(new SendWebhookMessage(
            new Subscriber($this->generateUrl('_webhook_controller', ['type' => 'payments'], UrlGeneratorInterface::ABSOLUTE_URL), $secret),
            new RemoteEvent($event, 'msg_'.bin2hex(random_bytes(8)), [
                // Standard Webhooks name the event in the payload, which the signature covers
                'type' => $event,
                'data' => ['payment' => $paymentId, 'reference' => $reference, 'amount' => $amount],
            ]),
        ));

        return $this->redirect($returnUrl, Response::HTTP_SEE_OTHER);
    }
}
