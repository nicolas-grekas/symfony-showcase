<?php

namespace App\MessageHandler;

use App\Message\ProposalSubmitted;
use App\Repository\ProposalRepository;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\Turbo\Helper\TurboStream;

/**
 * Refreshes the lists of proposals that the reviewers have open, through the Mercure hub.
 *
 * The topic is the path of the list, and the update is the "refresh" action of Turbo, without data: each page loads
 * the list again with the session of its reviewer. So the hub never sees a proposal, and the pages subscribe without
 * a token.
 */
#[AsMessageHandler]
final class RefreshProposalLists
{
    public function __construct(
        private readonly ProposalRepository $proposals,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly HubInterface $hub,
    ) {
    }

    public function __invoke(ProposalSubmitted $event): void
    {
        $proposal = $this->proposals->find($event->proposalId) ?? throw new UnrecoverableMessageHandlingException(\sprintf('Proposal "%s" not found.', $event->proposalId));

        $this->hub->publish(new Update(
            $this->urlGenerator->generate('admin_proposals', ['conference' => $proposal->conference->id]),
            TurboStream::refresh(),
        ));
    }
}
