<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Message\IngestContentMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:schedule:test-ingest',
    description: 'Test scheduled ingestion by dispatching the message immediately'
)]
final class TestScheduledIngestCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Testing Scheduled Ingestion');
        $io->info('Dispatching IngestContentMessage to async queue...');

        try {
            $this->messageBus->dispatch(new IngestContentMessage());

            $io->success('Message dispatched successfully!');
            $io->note([
                'The message has been sent to the async queue.',
                'Make sure the Messenger worker is running:',
                '  docker compose exec php bin/console messenger:consume async -vv',
                '',
                'Check the logs to see the ingestion process.'
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Failed to dispatch message: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
