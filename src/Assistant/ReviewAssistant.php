<?php

namespace App\Assistant;

use App\Entity\Proposal;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\Stream\Delta\PartialObjectDelta;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Writes notes about a proposal for the reviewers, with an agent whose tools only read. The reviewers decide.
 */
final class ReviewAssistant
{
    public function __construct(
        #[Target('reviewAssistant')]
        private readonly AgentInterface $agent,
        private readonly CacheInterface $cache,
        #[Autowire(env: 'AI_PLATFORM')]
        public readonly string $platform,
        #[Autowire(env: 'AI_CHAT_MODEL')]
        public readonly string $model,
    ) {
    }

    /**
     * Asks the model for notes, which are kept for the next reviewers.
     */
    public function review(Proposal $proposal): ReviewNotes
    {
        // an infinite beta computes the notes again
        return $this->cache->get($this->getCacheKey($proposal), fn () => $this->agent->call(
            $this->getPrompt($proposal),
            ['response_format' => ReviewNotes::class],
        )->asObject(ReviewNotes::class), \INF);
    }

    /**
     * Asks the model for notes and yields what it does as it does it: the tools it calls, then the notes as it writes
     * them. The notes are kept for the next reviewers.
     *
     * @return \Generator<int, ToolCall|ReviewNotes, void, ReviewNotes> the final notes, as return value
     */
    public function stream(Proposal $proposal): \Generator
    {
        $execution = $this->agent->call($this->getPrompt($proposal), ['response_format' => ReviewNotes::class, 'stream' => true]);

        $notes = null;
        foreach ($execution as $update) {
            $payload = $update instanceof Progress ? $update->getPayload() : null;

            if ($payload instanceof ToolCall) {
                yield $payload;
            } elseif ($payload instanceof PartialObjectDelta) {
                yield $notes = $payload->getObject();
            }
        }

        // the last partial notes are the whole answer when the profiler of the debug mode turns the final object into a text
        if (null === $notes || $execution->getResult() instanceof ObjectResult) {
            $notes = $execution->asObject(ReviewNotes::class);
        }

        return $this->cache->get($this->getCacheKey($proposal), static fn () => $notes, \INF);
    }

    /**
     * The notes that the model wrote already, if any.
     */
    public function getNotes(Proposal $proposal): ?ReviewNotes
    {
        return $this->cache->get($this->getCacheKey($proposal), static function (ItemInterface $item, bool &$save) {
            $save = false;

            return null;
        });
    }

    private function getPrompt(Proposal $proposal): string
    {
        return \sprintf('Review the proposal %s.', $proposal->id);
    }

    private function getCacheKey(Proposal $proposal): string
    {
        // the notes of another model are written again
        return 'review_notes.'.$proposal->id.'.'.hash('xxh128', $this->platform.$this->model);
    }
}
