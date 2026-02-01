<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use App\Domain\ValueObject\ContentType;
use DateTimeImmutable;

final readonly class ContentDTO
{
    public function __construct(
        public string $providerId,
        public string $title,
        public ContentType $type,
        public array $metrics,
        public DateTimeImmutable $publishedAt,
    ) {
    }
}
