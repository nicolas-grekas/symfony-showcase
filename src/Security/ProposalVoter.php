<?php

namespace App\Security;

use App\Entity\Proposal;
use App\Entity\Speaker;
use App\Entity\User;
use App\Enum\ProposalStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Proposal>
 */
final class ProposalVoter extends Voter
{
    public const VIEW = 'PROPOSAL_VIEW';
    public const WITHDRAW = 'PROPOSAL_WITHDRAW';
    public const REVIEW = 'PROPOSAL_REVIEW';
    public const DECIDE = 'PROPOSAL_DECIDE';

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
        private readonly RecentAuthentication $recentAuthentication,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Proposal && \in_array($attribute, [self::VIEW, self::WITHDRAW, self::REVIEW, self::DECIDE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return match ($attribute) {
            self::VIEW => ProposalStatus::Accepted === $subject->getStatus() || $this->isSpeaker($token, $subject) || $this->accessDecisionManager->decide($token, ['ROLE_REVIEWER']),
            self::WITHDRAW => $this->isSpeaker($token, $subject),
            self::REVIEW => $this->accessDecisionManager->decide($token, ['ROLE_REVIEWER']),
            self::DECIDE => $this->canDecide($token, $vote),
        };
    }

    private function canDecide(TokenInterface $token, ?Vote $vote): bool
    {
        if (!$this->accessDecisionManager->decide($token, ['ROLE_REVIEWER'])) {
            $vote?->addReason('Only reviewers decide on proposals.');

            return false;
        }

        // accepting or rejecting a proposal requires a recent login
        if (!$this->recentAuthentication->isRecent($token)) {
            // the firewall asks for the password again instead of denying access
            $this->recentAuthentication->requestReAuthentication($vote);

            return false;
        }

        return true;
    }

    private function isSpeaker(TokenInterface $token, Proposal $proposal): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $proposal->speakers->exists(static fn (int $key, Speaker $speaker) => $speaker->email === $user->email);
    }
}
