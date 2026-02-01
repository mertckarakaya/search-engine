<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider;

use App\Domain\DTO\ContentDTO;
use App\Domain\ValueObject\ContentType;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class XmlProvider implements ProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $feedUrl,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function fetch(int $limit = 30): array
    {
        $startTime = microtime(true);

        try {
            $this->logger?->info('Fetching from XML provider', [
                'url' => $this->feedUrl,
                'limit' => $limit,
            ]);

            $response = $this->httpClient->request('GET', $this->feedUrl, [
                'query' => ['limit' => $limit],
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/xml, text/xml',
                    'User-Agent' => 'SearchEngine/1.0',
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                $this->logger?->error('XML provider returned non-200 status', [
                    'status' => $statusCode,
                    'url' => $this->feedUrl,
                ]);
                return [];
            }

            $xmlContent = $response->getContent();

            // Suppress warnings for malformed XML
            libxml_use_internal_errors(true);
            $xml = new SimpleXMLElement($xmlContent);
            libxml_clear_errors();

            $contents = $this->transformData($xml);

            $duration = (microtime(true) - $startTime) * 1000;
            $this->logger?->info('XML provider fetch completed', [
                'count' => count($contents),
                'duration_ms' => round($duration, 2),
            ]);

            return $contents;

        } catch (TransportExceptionInterface $e) {
            $this->logger?->error('XML provider transport error', [
                'error' => $e->getMessage(),
                'url' => $this->feedUrl,
            ]);
            return [];
        } catch (\Exception $e) {
            $this->logger?->error('XML provider error', [
                'error' => $e->getMessage(),
                'url' => $this->feedUrl,
            ]);
            return [];
        }
    }

    public function getName(): string
    {
        return 'xml_provider';
    }

    /**
     * @param SimpleXMLElement $xml
     * @return ContentDTO[]
     */
    private function transformData(SimpleXMLElement $xml): array
    {
        $items = [];

        foreach ($xml->item as $item) {
            try {
                // Validate required fields
                if (!isset($item->id, $item->headline, $item->type, $item->publication_date)) {
                    $this->logger?->warning('XML item missing required fields');
                    continue;
                }

                $type = ContentType::from((string) $item->type);

                $metrics = match ($type) {
                    ContentType::VIDEO => [
                        'views' => isset($item->stats->views) ? (int) $item->stats->views : 0,
                        'likes' => isset($item->stats->likes) ? (int) $item->stats->likes : 0,
                        'duration' => isset($item->stats->duration) ? (string) $item->stats->duration : '0:00',
                    ],
                    ContentType::ARTICLE => [
                        'reading_time' => isset($item->stats->reading_time) ? (int) $item->stats->reading_time : 0,
                        'reactions' => isset($item->stats->reactions) ? (int) $item->stats->reactions : 0,
                    ],
                };

                $items[] = new ContentDTO(
                    providerId: (string) $item->id,
                    title: (string) $item->headline,
                    type: $type,
                    metrics: $metrics,
                    publishedAt: new DateTimeImmutable((string) $item->publication_date)
                );
            } catch (\Exception $e) {
                $this->logger?->warning('Failed to transform XML item', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $items;
    }
}
