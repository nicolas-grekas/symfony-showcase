<?php

namespace App\Mcp;

use Mcp\Capability\RegistryInterface;
use Mcp\Exception\ToolNotFoundException;
use Symfony\AI\McpBundle\Controller\McpController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Adds the #[IsGranted] attributes of the tools a request calls to the attributes of the MCP controller.
 *
 * Symfony then checks them as it checks those of any controller, before the MCP server runs: the server turns
 * every exception into a JSON-RPC error, while a denial thrown before it reaches the firewall, which answers a
 * missing scope with the "insufficient_scope" challenge of RFC 6750 that MCP clients act on.
 */
#[AsEventListener]
final class IsGrantedToolListener
{
    public function __construct(
        #[Autowire(service: 'mcp.server.conferences.registry')]
        private readonly RegistryInterface $registry,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        $controller = $event->getController();
        if (!\is_array($controller) || !$controller[0] instanceof McpController || !$event->getRequest()->isMethod('POST')) {
            return;
        }

        $attributes = [];
        foreach ($this->getCalledTools($event->getRequest()->getContent()) as $name) {
            try {
                $handler = $this->registry->getTool($name)->handler;
            } catch (ToolNotFoundException) {
                continue;
            }

            if (\is_array($handler) && method_exists(...$handler)) {
                foreach ((new \ReflectionMethod(...$handler))->getAttributes(IsGranted::class) as $attribute) {
                    $attributes[] = $attribute->newInstance();
                }
            }
        }

        if ($attributes) {
            $event->setController($controller, [...$event->getAttributes('*'), ...$attributes]);
        }
    }

    /**
     * @return iterable<string>
     */
    private function getCalledTools(string $body): iterable
    {
        $messages = json_decode($body, true);

        // a JSON-RPC batch is a list of messages
        foreach (\is_array($messages) && array_is_list($messages) ? $messages : [$messages] as $message) {
            if ('tools/call' === ($message['method'] ?? null) && \is_string($name = $message['params']['name'] ?? null)) {
                yield $name;
            }
        }
    }
}
