<?php

namespace App\Repository;

use App\Dto\EventInput;
use App\Entity\Event;

interface WriteEventRepository
{
    /**
     * Updates a comment of an existing event record in the database with the provided input data.
     *
     * @param EventInput $eventInput The input data to update the event with.
     * @param int $id The unique identifier of the event to update.
     *
     * @return void
     */
    public function update(EventInput $eventInput, int $id): void;

    /**
     * Creates a new event based on the provided Event object.
     *
     * @param Event $Event The event creation data transfer object containing event details.
     *
     * @return void
     */
    public function createOne(Event $event): void;

    public function createMany(array $events): void;

    public function upsertMany(array $events): void;
}
