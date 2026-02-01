<?php

declare(strict_types=1);

namespace App\Application\MessageHandler;

use App\Application\Message\IngestContentMessage;
use App\Application\Service\IngestionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class IngestContentHandler
{
    public function __construct(
        private readonly IngestionService $ingestionService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(IngestContentMessage $message): void
    {
        $this->logger->info('Scheduled content ingestion started', [
            'limit' => $message->getLimit(),
        ]);

        try {
            $result = $this->ingestionService->ingest($message->getLimit());

            $this->logger->info('Scheduled content ingestion completed', [
                'ingested' => $result['ingested'],
                'skipped' => $result['skipped'],
                'jobs_dispatched' => $result['dispatched'],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Scheduled content ingestion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
