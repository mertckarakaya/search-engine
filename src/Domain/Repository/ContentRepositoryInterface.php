<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Content;
use App\Domain\ValueObject\ContentType;

interface ContentRepositoryInterface
{
    public function save(Content $content): void;

    public function findById(int $id): ?Content;

    /**
     * @return Content[]
     */
    public function search(
        ?string $keyword = null,
        ?ContentType $type = null,
        int $page = 1,
        int $limit = 10
    ): array;

    public function countSearch(?string $keyword = null, ?ContentType $type = null): int;
}
