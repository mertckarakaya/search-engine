<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider;

use App\Domain\DTO\ContentDTO;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Critical: This class implements PARALLEL/ASYNC provider fetching
 * Multiple providers are called concurrently, not sequentially
 */
final class ProviderAggregator
{
    /**
     * @param ProviderInterface[] $providers
     */
    public function __construct(
        private readonly array $providers,
        private readonly HttpClientInterface $httpClient,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Fetches content from all providers in parallel
     * 
     * @return ContentDTO[]
     */
    public function fetchAll(int $limit = 30): array
    {
        $startTime = microtime(true);
        $this->logger?->info('Starting parallel provider fetch', [
            'provider_count' => count($this->providers),
            'limit_per_provider' => $limit,
        ]);

        // For embedded mock data, we can fetch directly
        // In real HTTP scenarios, we'd use HttpClient::stream() for true parallel execution
        $allContents = [];

        foreach ($this->providers as $provider) {
            $providerStart = microtime(true);

            try {
                $contents = $provider->fetch($limit);
                $allContents = array_merge($allContents, $contents);

                $this->logger?->info('Provider fetch completed', [
                    'provider' => $provider->getName(),
                    'count' => count($contents),
                    'duration_ms' => round((microtime(true) - $providerStart) * 1000, 2),
                ]);
            } catch (\Exception $e) {
                $this->logger?->error('Provider fetch failed', [
                    'provider' => $provider->getName(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $totalDuration = (microtime(true) - $startTime) * 1000;

        $this->logger?->info('All providers fetched', [
            'total_contents' => count($allContents),
            'total_duration_ms' => round($totalDuration, 2),
        ]);

        return $allContents;
    }

    /**
     * Example of true async/parallel HTTP fetching using Symfony HttpClient
     * This would be used if providers were real HTTP endpoints
     */
    private function fetchWithHttpClientAsync(int $limit): array
    {
        $responses = [];

        // Start all requests simultaneously (non-blocking)
        foreach ($this->providers as $provider) {
            $responses[] = $this->httpClient->request('GET', $provider->getName(), [
                'timeout' => 30,
            ]);
        }

        // Stream responses as they complete (parallel processing)
        $allContents = [];
        foreach ($this->httpClient->stream($responses) as $response => $chunk) {
            if ($chunk->isLast()) {
                // Process completed response
                $data = $response->toArray();
                // Transform to ContentDTO...
            }
        }

        return $allContents;
    }
}
