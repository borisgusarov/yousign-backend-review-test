<?php

namespace App\Repository;

use App\Dto\EventInput;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Event;


class DbalWriteEventRepository implements WriteEventRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function update(EventInput $eventInput, int $id): void
    {
        $event = $this->entityManager->getRepository(Event::class)->find($id);
        if (!$event) {
            throw new \RuntimeException("Event with ID $id not found.");
        }
        $event->setComment($eventInput->comment);
        $this->entityManager->flush();
    }

    public function createOne(Event $event): void
    {
        if ($event) {
            $this->entityManager->persist($event);
            $this->entityManager->flush();
        }
    }

    /**
     * Persists and flushes multiple Event entities in batches for efficiency.
     * 
     * @param Event[] $events
     */
    public function createMany(array $events): void
    {
        $batchSize = 100;
        $i = 0;
        foreach ($events as $event) {
            $this->entityManager->persist($event);
            $i++;
            if ($i % $batchSize === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear(Event::class);
            }
        }
        if ($i > 0) {
            $this->entityManager->flush();
            $this->entityManager->clear(Event::class);
        }
    }

    /**
     * Inserts new events if they don't exist (by unique constraint, e.g.,
     * id or another unique field), otherwise ignores.
     * 
     * @param Event[] $events
     */
    public function upsertMany(array $events): void
    {
        $batchSize = 100;
        $i = 0;
        foreach ($events as $event) {
            $existing = $this->entityManager->getRepository(Event::class)->find($event->id());
            if (!$existing) {
                $this->entityManager->persist($event);
            }
            $i++;
            if ($i % $batchSize === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear(Event::class);
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear(Event::class);
    }
}
