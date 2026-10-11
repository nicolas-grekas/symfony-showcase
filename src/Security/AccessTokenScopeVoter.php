<?php

namespace App\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Grants "SCOPE_<scope>" when the access token of the request was granted that OAuth2 scope.
 *
 * @extends Voter<string, mixed>
 */
final class AccessTokenScopeVoter extends Voter
{
    public const PREFIX = 'SCOPE_';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_starts_with($attribute, self::PREFIX);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $scope = substr($attribute, \strlen(self::PREFIX));

        if (!\in_array($scope, $token->hasAttribute(AccessTokenScopeListener::ATTRIBUTE) ? $token->getAttribute(AccessTokenScopeListener::ATTRIBUTE) : [], true)) {
            $vote?->addReason(\sprintf('The access token was not granted the "%s" scope.', $scope));

            return false;
        }

        return true;
    }
}
