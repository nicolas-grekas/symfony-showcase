<?php

namespace App\Api;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\ErrorHandler\ErrorRenderer\ErrorRendererInterface;
use Symfony\Component\ErrorHandler\Exception\FlattenException;

/**
 * Serves the problem documents that the serializer renders for errors in JSON as RFC 9457 "application/problem+json".
 */
#[AsDecorator('error_renderer.serializer')]
final class ProblemJsonErrorRenderer implements ErrorRendererInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly ErrorRendererInterface $inner,
    ) {
    }

    public function render(\Throwable $exception): FlattenException
    {
        $flattenException = $this->inner->render($exception);
        $headers = $flattenException->getHeaders();

        if ('application/json' === ($headers['Content-Type'] ?? null)) {
            $headers['Content-Type'] = 'application/problem+json';
            $flattenException->setHeaders($headers);
        }

        return $flattenException;
    }
}
