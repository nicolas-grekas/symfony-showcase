<?php

namespace App\Tests\Assistant;

use App\Assistant\ReviewNotes;
use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Bridge\PhpUnit\Attribute\UseRecord;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Replays the exchanges recorded with a local Ollama; run with SYMFONY_HTTP_RECORDER=record and Ollama running, with
 * the all-minilm model, to record them again.
 */
final class OllamaTest extends KernelTestCase
{
    #[UseRecord]
    public function testOllamaEmbedsTextsByTheirMeaning()
    {
        $platform = static::getContainer()->get('ai.platform.ollama');

        $vectors = $platform->invoke('all-minilm', ['The cache is cold after a deployment', 'Warming up the cache before the first request', 'Forms in 2027'])->asVectors();

        $this->assertCount(3, $vectors);
        $this->assertSame(384, $vectors[0]->getDimensions());
        $this->assertGreaterThan($this->similarity($vectors[0]->getData(), $vectors[2]->getData()) + 0.3, $this->similarity($vectors[0]->getData(), $vectors[1]->getData()));
    }

    public function testTheModelReadsWithItsToolsBeforeWritingTheNotes()
    {
        $event = $this->invoke(new MessageBag(Message::forSystem('You help the reviewers.'), Message::ofUser('Review the proposal 01a17e36.')));

        // a model of Ollama can't call a tool while it must answer in the response format
        $this->assertArrayNotHasKey('response_format', $event->getOptions());
        $this->assertArrayHasKey('tools', $event->getOptions());
        $this->assertFalse($event->getOptions()['think']);
    }

    public function testTheModelWritesTheNotesInTheFormatOnceItHasRead()
    {
        $toolCall = new ToolCall('call_1', 'get_proposal', ['id' => '01a17e36']);

        $event = $this->invoke(new MessageBag(Message::forSystem('You help the reviewers.'), Message::ofUser('Review the proposal 01a17e36.'), Message::ofAssistant($toolCall), Message::ofToolCall($toolCall, '{"title": "Forms in 2027"}')));

        $this->assertArrayHasKey('response_format', $event->getOptions());
        $this->assertFalse($event->getOptions()['think']);
    }

    public function testTheModelsOfOtherPlatformsAreLeftAsTheyAre()
    {
        $event = $this->invoke(new MessageBag(Message::ofUser('Review the proposal 01a17e36.')), new Model('openai/gpt-oss-120b', [Capability::INPUT_MESSAGES, Capability::TOOL_CALLING, Capability::OUTPUT_STRUCTURED]));

        $this->assertArrayHasKey('response_format', $event->getOptions());
        $this->assertArrayNotHasKey('think', $event->getOptions());
    }

    private function invoke(MessageBag $messages, ?Model $model = null): InvocationEvent
    {
        $model ??= new Ollama('qwen3:8b', [Capability::INPUT_MESSAGES, Capability::TOOL_CALLING, Capability::THINKING, Capability::OUTPUT_STRUCTURED]);
        $tool = new Tool(new ExecutionReference(self::class, 'getProposal'), 'get_proposal', 'Reads a proposal');
        $event = new InvocationEvent($model, $messages, ['tools' => [$tool], 'response_format' => ReviewNotes::class]);

        return static::getContainer()->get(EventDispatcherInterface::class)->dispatch($event);
    }

    /**
     * @param list<float> $a
     * @param list<float> $b
     */
    private function similarity(array $a, array $b): float
    {
        $dot = $normA = $normB = 0.0;
        foreach ($a as $i => $x) {
            $dot += $x * $b[$i];
            $normA += $x * $x;
            $normB += $b[$i] * $b[$i];
        }

        return $dot / sqrt($normA * $normB);
    }
}
