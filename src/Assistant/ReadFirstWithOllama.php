<?php

namespace App\Assistant;

use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Lets a model of Ollama read with its tools before it writes in the response format.
 *
 * Ollama constrains the whole answer to the response format, so a model that must answer in JSON can't call a tool. The
 * first round of a call goes without the format, and the model reads with its tools; the rounds after the results of
 * the tools answer in the format. The notes need no reasoning, so a model that can think answers without thinking,
 * which is faster.
 */
#[AsEventListener(priority: 10)]
final class ReadFirstWithOllama
{
    public function __invoke(InvocationEvent $event): void
    {
        if (!$event->getModel() instanceof Ollama) {
            return;
        }

        $options = $event->getOptions();

        if ($event->getModel()->supports(Capability::THINKING)) {
            $options['think'] ??= false;
        }

        $messages = $event->getInput();
        if (isset($options['tools'], $options[PlatformSubscriber::RESPONSE_FORMAT]) && $messages instanceof MessageBag && !$messages->isLastMessageFrom(Role::ToolCall)) {
            unset($options[PlatformSubscriber::RESPONSE_FORMAT]);
        }

        $event->setOptions($options);
    }
}
