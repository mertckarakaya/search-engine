<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider;

use App\Domain\DTO\ContentDTO;
use App\Domain\ValueObject\ContentType;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class JsonProvider implements ProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiUrl,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function fetch(int $limit = 30): array
    {
        $startTime = microtime(true);

        try {
            $this->logger?->info('Fetching from JSON provider', [
                'url' => $this->apiUrl,
                'limit' => $limit,
            ]);

            $response = $this->httpClient->request('GET', $this->apiUrl, [
                'query' => ['limit' => $limit],
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'SearchEngine/1.0',
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                $this->logger?->error('JSON provider returned non-200 status', [
                    'status' => $statusCode,
                    'url' => $this->apiUrl,
                ]);
                return [];
            }

            $data = $response->toArray();
            $contents = $this->transformData($data['items'] ?? $data);

            $duration = (microtime(true) - $startTime) * 1000;
            $this->logger?->info('JSON provider fetch completed', [
                'count' => count($contents),
                'duration_ms' => round($duration, 2),
            ]);

            return $contents;

        } catch (TransportExceptionInterface $e) {
            $this->logger?->error('JSON provider transport error', [
                'error' => $e->getMessage(),
                'url' => $this->apiUrl,
            ]);
            return [];
        } catch (\Exception $e) {
            $this->logger?->error('JSON provider error', [
                'error' => $e->getMessage(),
                'url' => $this->apiUrl,
            ]);
            return [];
        }
    }

    public function getName(): string
    {
        return 'json_provider';
    }

    /**
     * @param array $data
     * @return ContentDTO[]
     */
    private function transformData(array $data): array
    {
        $items = [];

        foreach ($data as $item) {
            try {
                // Validate required fields
                if (!isset($item['id'], $item['title'], $item['type'], $item['published_at'])) {
                    $this->logger?->warning('JSON item missing required fields', [
                        'item' => $item,
                    ]);
                    continue;
                }

                $items[] = new ContentDTO(
                    providerId: (string) $item['id'],
                    title: (string) $item['title'],
                    type: ContentType::from($item['type']),
                    metrics: $item['metrics'] ?? [],
                    publishedAt: new DateTimeImmutable($item['published_at'])
                );
            } catch (\Exception $e) {
                $this->logger?->warning('Failed to transform JSON item', [
                    'error' => $e->getMessage(),
                    'item' => $item,
                ]);
            }
        }

        return $items;
    }
}
