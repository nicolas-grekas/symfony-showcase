<?php

namespace App\Tests;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;

/**
 * Asserts on and consumes the messages queued on the in-memory Messenger transports of the test environment.
 */
trait MessengerAssertionsTrait
{
    /**
     * @param string|null $messageClass Counts only the messages that are instances of this class
     */
    public static function assertQueuedMessageCount(int $count, string $transport, ?string $messageClass = null, string $message = ''): void
    {
        $envelopes = self::getQueuedMessages($transport);

        if (null !== $messageClass) {
            if (!class_exists($messageClass) && !interface_exists($messageClass)) {
                throw new \InvalidArgumentException(\sprintf('The message class "%s" does not exist.', $messageClass));
            }

            $envelopes = array_filter($envelopes, static fn (Envelope $envelope) => $envelope->getMessage() instanceof $messageClass);
        }

        self::assertCount($count, $envelopes, $message ?: \sprintf('Failed asserting that the "%s" transport has %d queued message(s)%s.', $transport, $count, null === $messageClass ? '' : ' of class "'.$messageClass.'"'));
    }

    /**
     * Returns the envelopes sent to an in-memory transport and not acknowledged or rejected yet, delayed ones included.
     *
     * @return Envelope[]
     */
    public static function getQueuedMessages(string $transport): array
    {
        $transport = self::getMessengerTransport($transport);
        $processed = [];

        foreach ([...$transport->getAcknowledged(), ...$transport->getRejected()] as $envelope) {
            $processed[$envelope->last(TransportMessageIdStamp::class)->getId()] = true;
        }

        return array_values(array_filter($transport->getSent(), static fn (Envelope $envelope) => !isset($processed[$envelope->last(TransportMessageIdStamp::class)->getId()])));
    }

    /**
     * Handles the messages queued on an in-memory transport as a worker does.
     *
     * The worker listeners of the application send a failing message for retry or to the failure transport.
     * A message sent for retry is consumed again without waiting for its delay, and the failure that exhausts
     * its retries is rethrown. A message the application delayed itself stays queued until it is due.
     *
     * @param int|null $limit Stops after this number of messages, instead of draining the queue
     *
     * @return int The number of messages that were handled
     */
    public static function consumeQueuedMessages(string $transport, ?int $limit = null): int
    {
        $container = static::getContainer();
        $dispatcher = $container->get('event_dispatcher');
        $received = $handled = 0;
        $failures = [];
        $listeners = [
            WorkerRunningEvent::class => static function (WorkerRunningEvent $event) use ($limit, &$received): void {
                if ($event->isWorkerIdle() || null !== $limit && ++$received >= $limit) {
                    $event->getWorker()->stop();
                }
            },
            WorkerMessageHandledEvent::class => static function () use (&$handled): void {
                ++$handled;
            },
            WorkerMessageFailedEvent::class => static function (WorkerMessageFailedEvent $event) use (&$failures): void {
                // a failure the retry strategy replays is not the outcome of this run
                if (!$event->willRetry()) {
                    $failures[] = $event->getThrowable();
                }
            },
        ];

        foreach ($listeners as $eventName => $listener) {
            $dispatcher->addListener($eventName, $listener);
        }

        try {
            $receiver = self::createRetryAwareReceiver(self::getMessengerTransport($transport), static fn () => self::getQueuedMessages($transport));
            (new Worker([$transport => $receiver], $container->get('messenger.routable_message_bus'), $dispatcher))->run(['sleep' => 0]);
        } finally {
            foreach ($listeners as $eventName => $listener) {
                $dispatcher->removeListener($eventName, $listener);
            }
        }

        if ($failures) {
            throw $failures[0];
        }

        return $handled;
    }

    public static function getMessengerTransport(string $transport): InMemoryTransport
    {
        $container = static::getContainer();

        if (!$container->has($id = 'messenger.transport.'.$transport)) {
            static::fail(\sprintf('The "%s" Messenger transport is not registered.', $transport));
        }

        if (!($service = $container->get($id)) instanceof InMemoryTransport) {
            static::fail(\sprintf('The "%s" Messenger transport is not an in-memory transport. Configure "in-memory://" as its DSN in the test environment.', $transport));
        }

        return $service;
    }

    /**
     * Reads the messages sent for retry without waiting for their delay, so that a test does not have to.
     *
     * @param \Closure(): Envelope[] $queue
     */
    private static function createRetryAwareReceiver(InMemoryTransport $transport, \Closure $queue): ReceiverInterface
    {
        return new class($transport, $queue) implements ReceiverInterface {
            public function __construct(
                private InMemoryTransport $transport,
                private \Closure $queue,
            ) {
            }

            public function get(): iterable
            {
                if ($envelopes = $this->transport->get()) {
                    return $envelopes;
                }

                foreach (($this->queue)() as $envelope) {
                    if ($envelope->last(RedeliveryStamp::class)) {
                        return [$envelope];
                    }
                }

                return [];
            }

            public function ack(Envelope $envelope): void
            {
                $this->transport->ack($envelope);
            }

            public function reject(Envelope $envelope): void
            {
                $this->transport->reject($envelope);
            }
        };
    }
}
