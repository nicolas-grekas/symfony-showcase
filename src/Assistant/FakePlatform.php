<?php

namespace App\Assistant;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\CompositeModelCatalog;
use Symfony\AI\Platform\ModelCatalog\FallbackModelCatalog;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Test\MockModelCatalog;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Stands in for a model provider when AI_PLATFORM is "fake": in development without an API key, and in the tests.
 *
 * It is the mock platform of Symfony AI with scripted answers. The embeddings count the words of the texts, so texts
 * that share words are similar. The chat model reads the proposal with the tools of the review assistant. Then it takes
 * the first sentence of the abstract as the summary, adds the closest talk or proposal, and takes the most frequent
 * words as the tags. Asked to stream, it writes its answer a few characters at a time, at the pace of the clock.
 */
#[AutoconfigureTag('ai.platform', ['name' => 'fake'])]
final class FakePlatform implements PlatformInterface
{
    /** The size of the "embedding" column of the proposals */
    public const DIMENSIONS = 384;

    private const STOP_WORDS = ['about', 'after', 'and', 'are', 'but', 'can', 'for', 'from', 'has', 'have', 'how', 'into', 'its', 'not', 'one', 'our', 'out', 'over', 'that', 'the', 'their', 'them', 'then', 'they', 'this', 'what', 'when', 'where', 'which', 'who', 'why', 'will', 'with', 'you', 'your'];

    private readonly PlatformInterface $platform;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        #[Autowire(env: 'AI_EMBEDDING_MODEL')]
        string $embeddingModel,
        private readonly ClockInterface $clock,
    ) {
        // only the chat models take messages, so a text to embed is not turned into a message
        $models = new CompositeModelCatalog([
            new MockModelCatalog([$embeddingModel => ['class' => Model::class, 'capabilities' => [Capability::INPUT_TEXT, Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS]]]),
            new FallbackModelCatalog(),
        ]);

        $this->platform = MockPlatformFactory::createPlatform($this->answer(...), $models, eventDispatcher: $eventDispatcher, name: 'fake');
    }

    public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
    {
        return $this->platform->invoke($model, $input, $options);
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->platform->getModelCatalog();
    }

    public static function embed(string $text): Vector
    {
        $counts = array_fill(0, self::DIMENSIONS, 0);
        foreach (self::words($text) as $word) {
            ++$counts[crc32($word) % self::DIMENSIONS];
        }

        $norm = sqrt(array_sum(array_map(static fn (int $count) => $count ** 2, $counts))) ?: 1;

        return new Vector(array_map(static fn (int $count) => $count / $norm, $counts));
    }

    private function answer(Model $model, array|string|object $input, array $options): ResultInterface
    {
        if (!\is_array($input) || !isset($input['messages'])) {
            return new VectorResult(array_map(self::embed(...), (array) $input));
        }

        $results = $this->getToolResults($input['messages']);
        if (!$results) {
            preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/', json_encode(end($input['messages'])['content']), $id);

            $toolCalls = [
                new ToolCall('fake_1', 'get_proposal', ['id' => $id[0] ?? '']),
                new ToolCall('fake_2', 'find_similar_proposals', ['id' => $id[0] ?? '']),
                new ToolCall('fake_3', 'list_tracks'),
            ];

            return ($options['stream'] ?? false) ? new StreamResult($this->think(new ToolCallComplete($toolCalls))) : new ToolCallResult($toolCalls);
        }

        $proposal = $results['get_proposal'] ?? null;
        if (!\is_array($proposal)) {
            return $this->write(['summary' => \sprintf('The proposal could not be read: %s', $proposal), 'tags' => [], 'track' => null], $options);
        }

        $words = array_count_values(self::words($proposal['title'].' '.$proposal['abstract']));
        arsort($words);

        $summary = preg_split('/(?<=[.!?])\s/', trim($proposal['abstract']), 2)[0];
        foreach ($results['find_similar_proposals'] ?? [] as $similar) {
            if (0.5 <= $similar['similarity']) {
                $summary .= \sprintf(' It is close to "%s" (%s, %s).', $similar['title'], $similar['conference'], $similar['status']);

                break;
            }
        }

        $track = null;
        $best = 0;
        foreach ($results['list_tracks'] ?? [] as $candidate) {
            if ($best < $score = \count(array_intersect_key($words, array_flip(self::words($candidate['description']))))) {
                [$track, $best] = [$candidate['track'], $score];
            }
        }

        return $this->write(['summary' => $summary, 'tags' => \array_slice(array_keys($words), 0, 3), 'track' => $track], $options);
    }

    private function write(array $answer, array $options): ResultInterface
    {
        $json = json_encode($answer, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        if (!($options['stream'] ?? false)) {
            return new TextResult($json);
        }

        // tokens of up to four characters, with the spaces before them
        preg_match_all('/\s*\S{1,4}/u', $json, $tokens);

        return new StreamResult($this->think(...array_map(static fn (string $token) => new TextDelta($token), $tokens[0])));
    }

    /**
     * @return \Generator<DeltaInterface>
     */
    private function think(DeltaInterface ...$deltas): \Generator
    {
        foreach ($deltas as $delta) {
            $this->clock->sleep($delta instanceof TextDelta ? .03 : .5);

            yield $delta;
        }
    }

    /**
     * @return array<string, mixed> the decoded result of each tool, by tool name
     */
    private function getToolResults(array $messages): array
    {
        $names = [];
        $results = [];
        foreach ($messages as $message) {
            foreach ($message['tool_calls'] ?? [] as $call) {
                $names[$call['id']] = $call['function']['name'];
            }

            if ('tool' === $message['role']) {
                $results[$names[$message['tool_call_id']] ?? ''] = json_decode($message['content'], true) ?? $message['content'];
            }
        }

        return $results;
    }

    /**
     * @return list<string>
     */
    private static function words(string $text): array
    {
        preg_match_all('/\p{L}{3,}/u', mb_strtolower($text), $words);

        return array_values(array_diff($words[0], self::STOP_WORDS));
    }
}
