<?php

namespace App\Assistant;

use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Denies the calls to the tools that are not marked read-only, so that a tool given to an agent later cannot change
 * data before a reviewer decides to.
 */
#[AsEventListener]
final class DenyWritingTools
{
    public function __invoke(ToolCallRequested $event): void
    {
        if (!$event->getDefinition()->isReadOnly()) {
            $event->deny(\sprintf('The tool "%s" can change data: the assistant only reads, and the reviewers decide.', $event->getToolCall()->getName()));
        }
    }
}
