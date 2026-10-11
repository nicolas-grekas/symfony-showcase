<?php

namespace App\Messenger;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\SentStamp;

/**
 * Logs the processing time and memory usage of each message.
 */
#[WithMonologChannel('messenger')]
final class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $context = ['class' => $envelope->getMessage()::class];
        $startTime = hrtime(true);
        $startMemory = memory_get_usage();

        try {
            $envelope = $stack->next()->handle($envelope, $stack);
        } catch (\Throwable $e) {
            $this->logger->error('Unable to handle "{class}" message.', $context + self::usage($startTime, $startMemory) + ['exception' => $e]);

            throw $e;
        }

        $this->logger->info($envelope->last(SentStamp::class) ? '"{class}" message sent to transport.' : '"{class}" message successfully handled.', $context + self::usage($startTime, $startMemory));

        return $envelope;
    }

    private static function usage(int $startTime, int $startMemory): array
    {
        return [
            'duration_ms' => (int) round((hrtime(true) - $startTime) / 1e6),
            'memory_usage' => memory_get_usage() - $startMemory,
        ];
    }
}
