<?php

namespace App\Repository;

use App\Entity\Actor;
use Doctrine\ORM\EntityManagerInterface;

class DbalWriteActorRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function createOne(Actor $actor): void
    {
        if ($actor) {
            $this->entityManager->persist($actor);
            $this->entityManager->flush();
        }
    }

    /**
     * Persists and flushes multiple Actor entities in batches for efficiency.
     * 
     * @param Actor[] $actors
     */
    public function createMany(array $actors): void
    {
        $batchSize = 100;
        $i = 0;
        foreach ($actors as $actor) {
            $this->entityManager->persist($actor);
            $i++;
            if ($i % $batchSize === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear(Actor::class);
            }
        }
        if ($i % $batchSize !== 0) {
            $this->entityManager->flush();
            $this->entityManager->clear(Actor::class);
        }
    }

    /**
     * Inserts new actors if they don't exist (by unique constraint, e.g., 
     * id or another unique field), otherwise ignores.
     * 
     * @param Actor[] $actors
     */
    public function upsertMany(array $actors): void
    {
        $batchSize = 100;
        $i = 0;
        foreach ($actors as $actor) {
            $existing = $this->entityManager->getRepository(Actor::class)->find($actor->id());
            if (!$existing) {
                $this->entityManager->persist($actor);
            }
            $i++;
            if ($i % $batchSize === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear(Actor::class);
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear(Actor::class);
    }
}
