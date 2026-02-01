<?php

declare(strict_types=1);

namespace App\Application\Message;

final class IngestContentMessage
{
    public function __construct(
        private readonly ?int $limit = 30
    ) {
    }

    public function getLimit(): int
    {
        return $this->limit ?? 30;
    }
}
