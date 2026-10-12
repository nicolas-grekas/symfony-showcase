<?php

namespace App\Showcase;

use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;

/**
 * Lists the features of Symfony 8.2 a request used, with links to their code, their documentation and their 8.1 and 7.4 versions.
 */
#[When('dev')]
final class FeatureCollector extends AbstractDataCollector
{
    public const REPOSITORY = 'https://github.com/nicolas-grekas/symfony-showcase';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly ?TokenStorageInterface $tokenStorage = null,
    ) {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $token = $this->tokenStorage?->getToken();
        $route = $request->attributes->get('_route');
        $mediaType = strtok((string) $response->headers->get('Content-Type'), ';');
        $used = $appWide = [];

        foreach (Feature::cases() as $feature) {
            $details = $feature->details();

            if ($details->isAppWide()) {
                $appWide[] = $this->describe($feature);
            } elseif (\in_array($route, $details->routes, true)
                || (null !== $details->header && $response->headers->has($details->header))
                || (null !== $details->mediaType && $details->mediaType === $mediaType)
                || ($details->loggedIn && null !== $token)
                || ($details->impersonating && $token instanceof SwitchUserToken)
                || ($details->bearerToken && str_starts_with((string) $request->headers->get('Authorization'), 'Bearer '))
            ) {
                $used[] = $this->describe($feature);
            }
        }

        $this->data = ['used' => $used, 'app_wide' => $appWide];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getUsed(): array
    {
        return $this->data['used'] ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getAppWide(): array
    {
        return $this->data['app_wide'] ?? [];
    }

    public static function getTemplate(): string
    {
        return 'data_collector/showcase.html.twig';
    }

    private function describe(Feature $feature): array
    {
        $details = $feature->details();
        $code = [];

        foreach ($details->code as [$path, $text]) {
            $line = $this->findLine($path, $text);
            $code[] = [
                'path' => $path,
                'file' => $this->projectDir.'/'.$path,
                'line' => $line,
                'url' => self::REPOSITORY.'/blob/main/'.$path.($line ? '#L'.$line : ''),
            ];
        }

        return [
            'id' => $feature->value,
            'title' => $details->title,
            'summary' => $details->summary,
            'code' => $code,
            'docs' => null !== $details->docs ? 'https://symfony.com/doc/8.2/'.$details->docs.'.html' : null,
            'symfony81' => array_map(self::commit(...), $details->symfony81),
            'symfony74' => array_map(self::commit(...), $details->symfony74),
        ];
    }

    private static function commit(string $commit): array
    {
        return ['commit' => $commit, 'url' => self::REPOSITORY.'/commit/'.$commit];
    }

    private function findLine(string $path, string $text): ?int
    {
        foreach (@file($this->projectDir.'/'.$path) ?: [] as $i => $line) {
            if (str_contains($line, $text)) {
                return $i + 1;
            }
        }

        return null;
    }
}
