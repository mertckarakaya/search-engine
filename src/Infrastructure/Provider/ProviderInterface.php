<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider;

use App\Domain\DTO\ContentDTO;

interface ProviderInterface
{
    /**
     * @return ContentDTO[]
     */
    public function fetch(int $limit = 30): array;

    public function getName(): string;
}
