<?php

namespace App\Repository;

use App\Entity\Actor;

interface WriteActorRepository
{
    /**
     * Creates a new actor entity.
     *
     * @param Actor $actor The actor entity to persist.
     *
     * @return void
     */
    public function createOne(Actor $actor): void;

    /**
     * Persists and flushes multiple Actor entities in batches for efficiency.
     *
     * @param Actor[] $actors
     *
     * @return void
     */
    public function createMany(array $actors): void;

    /**
     * Inserts new actors if they don't exist (by unique constraint, e.g., id or another unique field), otherwise ignores.
     *
     * @param Actor[] $actors
     *
     * @return void
     */
    public function upsertMany(array $actors): void;
}
