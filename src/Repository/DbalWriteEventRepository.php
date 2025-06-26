<?php

namespace App\Repository;

use App\Dto\EventInput;
use App\Dto\SearchInput;
use Doctrine\DBAL\Connection;
use phpDocumentor\Reflection\DocBlock\Tags\Author;

class DbalWriteEventRepository implements WriteEventRepository
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function update(EventInput $eventInput, int $id): void
    {
        $sql = <<<SQL
        UPDATE event
        SET comment = :comment
        WHERE id = :id
SQL;

        $this->connection->executeQuery($sql, ['id' => $id, 'comment' => $eventInput->comment]);
    }
}
