<?php

namespace App\Assistant;

use App\Entity\Proposal;
use App\Message\EmbedProposal;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Embeds the new proposals and the ones whose title or abstract changed through Messenger, so that a worker calls
 * the model, not the request that saves the proposal.
 */
#[AsDoctrineListener(Events::onFlush)]
#[AsDoctrineListener(Events::postFlush)]
final class EmbedChangedProposals
{
    /** @var list<Uuid> */
    private array $changed = [];

    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof Proposal) {
                $this->changed[] = $entity->id;
            }
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            // the fields of the #[Embedding] attribute of Proposal::$embedding
            if ($entity instanceof Proposal && array_intersect_key($unitOfWork->getEntityChangeSet($entity), ['title' => true, 'abstract' => true])) {
                $this->changed[] = $entity->id;
            }
        }
    }

    public function postFlush(): void
    {
        [$changed, $this->changed] = [$this->changed, []];

        // inside the transaction of the flush, if any: the async transport stores the messages in the same database
        foreach ($changed as $id) {
            $this->bus->dispatch(new EmbedProposal($id));
        }
    }
}
