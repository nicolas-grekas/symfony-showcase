<?php

namespace App\Security;

use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * Tells what an anonymous visitor is allowed to do, whoever is logged in.
 */
final class GuestAuthorizationChecker
{
    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    #[AsTwigFunction('is_granted_for_guest')]
    public function isGranted(mixed $attribute, mixed $subject = null): bool
    {
        return $this->accessDecisionManager->decide(new NullToken(), [$attribute], $subject);
    }
}
