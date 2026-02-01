<?php

declare(strict_types=1);

namespace App\Application\MessageHandler;

use App\Application\Message\CalculateScoreMessage;
use App\Application\Service\ScoringService;
use App\Domain\Repository\ContentRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CalculateScoreHandler
{
    public function __construct(
        private readonly ContentRepositoryInterface $contentRepository,
        private readonly ScoringService $scoringService,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function __invoke(CalculateScoreMessage $message): void
    {
        $this->logger?->info('Processing score calculation message', [
            'content_id' => $message->contentId,
        ]);

        $content = $this->contentRepository->findById($message->contentId);

        if (!$content) {
            $this->logger?->error('Content not found for scoring', [
                'content_id' => $message->contentId,
            ]);
            return;
        }

        $score = $this->scoringService->calculateScore($content);
        $content->setScore($score);

        $this->contentRepository->save($content);

        $this->logger?->info('Score calculation completed', [
            'content_id' => $message->contentId,
            'score' => $score,
        ]);
    }
}
