<?php

namespace App\Repository;

use App\Entity\Repo;

interface WriteRepoRepository
{
    /**
     * Creates a new repo entity.
     *
     * @param Repo $repo The repo entity to persist.
     *
     * @return void
     */
    public function createOne(Repo $repo): void;

    /**
     * Persists and flushes multiple Repo entities in batches for efficiency.
     *
     * @param Repo[] $repos
     *
     * @return void
     */
    public function createMany(array $repos): void;

    /**
     * Inserts new repos if they don't exist (by unique constraint, e.g., id or another unique field), otherwise ignores.
     *
     * @param Repo[] $repos
     *
     * @return void
     */
    public function upsertMany(array $repos): void;
}
