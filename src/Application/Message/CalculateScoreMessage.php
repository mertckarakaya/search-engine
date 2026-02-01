<?php

declare(strict_types=1);

namespace App\Application\Message;

final readonly class CalculateScoreMessage
{
    public function __construct(
        public int $contentId
    ) {
    }
}
