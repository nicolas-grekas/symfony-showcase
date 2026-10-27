<?php

namespace App\Assistant;

use App\Entity\Proposal;
use Symfony\AI\Agent\AgentInterface;
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
            \sprintf('Review the proposal %s.', $proposal->id),
            ['response_format' => ReviewNotes::class],
        )->asObject(ReviewNotes::class), \INF);
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

    private function getCacheKey(Proposal $proposal): string
    {
        // the notes of another model are written again
        return 'review_notes.'.$proposal->id.'.'.hash('xxh128', $this->platform.$this->model);
    }
}
