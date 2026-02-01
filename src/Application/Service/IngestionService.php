<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Message\CalculateScoreMessage;
use App\Domain\Entity\Content;
use App\Domain\Repository\ContentRepositoryInterface;
use App\Infrastructure\Provider\ProviderAggregator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class IngestionService
{
    public function __construct(
        private readonly ProviderAggregator $providerAggregator,
        private readonly ContentRepositoryInterface $contentRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Fetches content from all providers and dispatches scoring messages
     * 
     * @return array{ingested: int, skipped: int, dispatched: int}
     */
    public function ingest(int $limit = 30): array
    {
        $this->logger?->info('Starting content ingestion', ['limit' => $limit]);

        $contentDtos = $this->providerAggregator->fetchAll($limit);

        $stats = [
            'ingested' => 0,
            'skipped' => 0,
            'dispatched' => 0,
        ];

        foreach ($contentDtos as $dto) {
            try {
                // Check if content already exists
                $existing = $this->contentRepository->search($dto->providerId, null, 1, 1);
                if (!empty($existing)) {
                    $stats['skipped']++;
                    continue;
                }

                // Create and save content with null score initially
                $content = new Content(
                    providerId: $dto->providerId,
                    title: $dto->title,
                    type: $dto->type,
                    metrics: $dto->metrics,
                    publishedAt: $dto->publishedAt
                );

                $this->contentRepository->save($content);
                $stats['ingested']++;

                // Dispatch async message for score calculation
                $this->messageBus->dispatch(new CalculateScoreMessage($content->getId()));
                $stats['dispatched']++;

                $this->logger?->info('Content ingested', [
                    'content_id' => $content->getId(),
                    'provider_id' => $dto->providerId,
                    'title' => $dto->title,
                ]);

            } catch (\Exception $e) {
                $this->logger?->error('Failed to ingest content', [
                    'provider_id' => $dto->providerId,
                    'error' => $e->getMessage(),
                ]);
                $stats['skipped']++;
            }
        }

        $this->logger?->info('Ingestion completed', $stats);

        return $stats;
    }
}
