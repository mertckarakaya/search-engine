<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

enum ContentType: string
{
    case VIDEO = 'video';
    case ARTICLE = 'article';

    public function getScoreCoefficient(): float
    {
        return match($this) {
            self::VIDEO => 1.5,
            self::ARTICLE => 1.0,
        };
    }
}
