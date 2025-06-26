<?php

namespace App\Repository;

use App\Entity\Repo;
use Doctrine\ORM\EntityManagerInterface;

class DbalWriteRepoRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function createOne(Repo $repo): void
    {
        if ($repo) {
            $this->entityManager->persist($repo);
            $this->entityManager->flush();
        }
    }

    /**
     * Persists and flushes multiple Repo entities in batches for efficiency.
     * 
     * @param Repo[] $repos
     */
    public function createMany(array $repos): void
    {
        $batchSize = 100;
        $i = 0;
        foreach ($repos as $repo) {
            $this->entityManager->persist($repo);
            $i++;
            if ($i % $batchSize === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear(Repo::class);
            }
        }
        if ($i > 0) {
            $this->entityManager->flush();
            $this->entityManager->clear(Repo::class);
        }
    }

    /**
     * Inserts new repos if they don't exist (by unique constraint, e.g., 
     * id or another unique field), otherwise ignores.
     * 
     * @param Repo[] $repos
     */
    public function upsertMany(array $repos): void
    {
        $batchSize = 100;
        $i = 0;
        foreach ($repos as $repo) {
            $existing = $this->entityManager->getRepository(Repo::class)->find($repo->id());
            if (!$existing) {
                $this->entityManager->persist($repo);
            }
            $i++;
            if ($i % $batchSize === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear(Repo::class);
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear(Repo::class);
    }
}
