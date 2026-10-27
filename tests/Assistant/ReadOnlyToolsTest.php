<?php

namespace App\Tests\Assistant;

use App\Assistant\ReviewTools;
use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\AI\Platform\Tool\ToolEffectHints;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class ReadOnlyToolsTest extends KernelTestCase
{
    public function testToolsThatAreNotReadOnlyAreDenied()
    {
        $event = new ToolCallRequested(new ToolCall('call_1', 'accept_proposal'), new Tool(new ExecutionReference(ReviewTools::class, 'acceptProposal'), 'accept_proposal', 'Accepts a proposal'));

        static::getContainer()->get(EventDispatcherInterface::class)->dispatch($event);

        $this->assertTrue($event->isDenied());
        $this->assertSame('The tool "accept_proposal" can change data: the assistant only reads, and the reviewers decide.', $event->getDenialReason());
    }

    public function testReadOnlyToolsRun()
    {
        $event = new ToolCallRequested(new ToolCall('call_1', 'get_proposal'), new Tool(new ExecutionReference(ReviewTools::class, 'getProposal'), 'get_proposal', 'Reads a proposal', effectHints: new ToolEffectHints(readOnlyHint: true)));

        static::getContainer()->get(EventDispatcherInterface::class)->dispatch($event);

        $this->assertFalse($event->isDenied());
    }
}
