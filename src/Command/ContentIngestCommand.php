<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Service\IngestionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ingest',
    description: 'Ingest content from providers and dispatch scoring jobs'
)]
final class ContentIngestCommand extends Command
{
    public function __construct(
        private readonly IngestionService $ingestionService,
        private readonly ?LoggerInterface $logger = null
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'limit',
            'l',
            InputOption::VALUE_OPTIONAL,
            'Number of items to fetch per provider',
            30
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int) $input->getOption('limit');

        $io->title('Content Ingestion');
        $io->info(sprintf('Fetching up to %d items per provider...', $limit));

        try {
            $stats = $this->ingestionService->ingest($limit);

            $io->success('Ingestion completed successfully!');
            $io->table(
                ['Metric', 'Count'],
                [
                    ['Ingested', $stats['ingested']],
                    ['Skipped (already exists)', $stats['skipped']],
                    ['Scoring jobs dispatched', $stats['dispatched']],
                ]
            );

            $io->note([
                'Scoring jobs have been dispatched to the async queue.',
                'Make sure the Messenger worker is running:',
                '  php bin/console messenger:consume async -vv',
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Ingestion failed: ' . $e->getMessage());
            $this->logger?->error('Ingestion command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
