<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Entity\Content;
use App\Domain\ValueObject\ContentType;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;

final class ScoringService
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Calculates final score based on the formula:
     * Final Score = (Base * TypeCoef) + Freshness + Interaction
     */
    public function calculateScore(Content $content): float
    {
        $baseScore = $this->calculateBaseScore($content);
        $typeCoefficient = $content->getType()->getScoreCoefficient();
        $freshnessScore = $this->calculateFreshnessScore($content->getPublishedAt());
        $interactionScore = $this->calculateInteractionScore($content);

        $finalScore = ($baseScore * $typeCoefficient) + $freshnessScore + $interactionScore;

        $this->logger?->info('Score calculated', [
            'content_id' => $content->getId(),
            'title' => $content->getTitle(),
            'type' => $content->getType()->value,
            'base_score' => round($baseScore, 2),
            'type_coefficient' => $typeCoefficient,
            'freshness_score' => $freshnessScore,
            'interaction_score' => round($interactionScore, 2),
            'final_score' => round($finalScore, 2),
        ]);

        return round($finalScore, 2);
    }

    private function calculateBaseScore(Content $content): float
    {
        $metrics = $content->getMetrics();

        return match ($content->getType()) {
            ContentType::VIDEO => ($metrics['views'] / 1000) + ($metrics['likes'] / 100),
            ContentType::ARTICLE => $metrics['reading_time'] + ($metrics['reactions'] / 50),
        };
    }

    private function calculateFreshnessScore(DateTimeImmutable $publishedAt): int
    {
        $now = new DateTimeImmutable();
        $diff = $now->diff($publishedAt);
        $daysDiff = (int) $diff->format('%a');

        return match (true) {
            $daysDiff <= 7 => 5,
            $daysDiff <= 30 => 3,
            $daysDiff <= 90 => 1,
            default => 0,
        };
    }

    private function calculateInteractionScore(Content $content): float
    {
        $metrics = $content->getMetrics();

        return match ($content->getType()) {
            ContentType::VIDEO => ($metrics['likes'] / max($metrics['views'], 1)) * 10,
            ContentType::ARTICLE => ($metrics['reactions'] / max($metrics['reading_time'], 1)) * 5,
        };
    }
}
