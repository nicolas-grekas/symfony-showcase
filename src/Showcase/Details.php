<?php

namespace App\Showcase;

/**
 * Describes how the app uses a feature of Symfony 8.2.
 */
#[\Attribute(\Attribute::TARGET_CLASS_CONSTANT)]
final readonly class Details
{
    /**
     * @param list<array{string, string}> $code       The files that use the feature, each with a text of the line to link to
     * @param string|null                 $docs       The path of the documentation page on symfony.com/doc/8.2
     * @param list<string>                $symfony81  The commits of the symfony-8.1 branch that do without the feature
     * @param list<string>                $symfony74  The commits of the symfony-7.4 branch that change the 8.1 code of the feature again
     * @param list<string>                $routes     The routes whose requests use the feature
     * @param string|null                 $header     A header of the response that tells the request used the feature
     * @param string|null                 $mediaType  The media type of the responses that use the feature
     * @param bool                        $loggedIn   Whether every page seen by a logged in user uses the feature
     * @param bool                        $impersonating Whether every page seen while impersonating a user uses the feature
     * @param bool                        $bearerToken Whether every request authenticated with an access token uses the feature
     */
    public function __construct(
        public string $title,
        public string $summary,
        public array $code = [],
        public ?string $docs = null,
        public array $symfony81 = [],
        public array $symfony74 = [],
        public array $routes = [],
        public ?string $header = null,
        public ?string $mediaType = null,
        public bool $loggedIn = false,
        public bool $impersonating = false,
        public bool $bearerToken = false,
    ) {
    }

    /**
     * Whether no request in particular uses the feature, because the whole app does.
     */
    public function isAppWide(): bool
    {
        return !$this->routes && null === $this->header && null === $this->mediaType && !$this->loggedIn && !$this->impersonating && !$this->bearerToken;
    }
}
