<?php

namespace App\Tests\MessageHandler;

use App\Entity\Proposal;
use App\Message\ProposalDecided;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Email;

final class HandleProposalDecisionTest extends KernelTestCase
{
    public function testTheOrganizersAreAlertedWhenTheTravelOfASpeakerCannotBeBooked()
    {
        self::bootKernel();
        static::getContainer()->set('http_client.transport', new MockHttpClient(new MockResponse('', ['http_code' => 503])));
        $proposal = static::getContainer()->get(EntityManagerInterface::class)->getRepository(Proposal::class)->findOneBy(['title' => 'Workflows as code']);

        static::getContainer()->get(MessageBusInterface::class)->dispatch(new ProposalDecided($proposal->id, true));
        static::consumeQueuedMessages('outbox');
        static::consumeQueuedMessages('events');

        // the booking is retried, then given up
        static::consumeQueuedMessages('outbox');
        try {
            static::consumeQueuedMessages('events');
            $this->fail('The booking should have failed.');
        } catch (HandlerFailedException) {
        }

        static::consumeQueuedMessages('async');
        $alerts = array_values(array_filter(
            static::getMailerMessages(),
            static fn ($message) => $message instanceof Email && 'The travel of a speaker could not be booked' === $message->getSubject(),
        ));
        $this->assertCount(1, $alerts);
        $this->assertSame('admin@example.com', $alerts[0]->getTo()[0]->getAddress());
        $this->assertStringContainsString('Book it by hand for "Workflows as code".', $alerts[0]->getHtmlBody() ?? $alerts[0]->getTextBody());
    }
}
