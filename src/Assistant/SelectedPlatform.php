<?php

namespace App\Assistant;

use Psr\Container\ContainerInterface;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

/**
 * The platform that AI_PLATFORM names, so that .env picks the provider: "huggingface", "fake", or any platform
 * of config/packages/ai.yaml.
 */
final class SelectedPlatform implements PlatformInterface
{
    public function __construct(
        #[AutowireLocator('ai.platform', indexAttribute: 'name')]
        private readonly ContainerInterface $platforms,
        #[Autowire(env: 'AI_PLATFORM')]
        private readonly string $name,
    ) {
    }

    public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
    {
        return $this->platforms->get($this->name)->invoke($model, $input, $options);
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->platforms->get($this->name)->getModelCatalog();
    }
}
