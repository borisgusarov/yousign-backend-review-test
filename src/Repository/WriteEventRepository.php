<?php

namespace App\Repository;

use App\Dto\EventInput;

interface WriteEventRepository
{
    public function update(EventInput $eventInput, int $id): void;
}
