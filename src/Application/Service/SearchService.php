<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Repository\ContentRepositoryInterface;
use App\Domain\ValueObject\ContentType;
use App\Infrastructure\Cache\RedisCacheAdapter;
use Psr\Log\LoggerInterface;

final class SearchService
{
    public function __construct(
        private readonly ContentRepositoryInterface $contentRepository,
        private readonly RedisCacheAdapter $cache,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Search with Redis caching (FIFO, max 10 queries)
     */
    public function search(
        ?string $keyword = null,
        ?string $type = null,
        int $page = 1,
        int $limit = 10
    ): array {
        $contentType = $type ? ContentType::from($type) : null;
        $cacheKey = $this->generateCacheKey($keyword, $type, $page, $limit);

        // Try cache first
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Query database
        $contents = $this->contentRepository->search($keyword, $contentType, $page, $limit);
        $total = $this->contentRepository->countSearch($keyword, $contentType);

        $result = [
            'data' => array_map(fn($content) => $this->serializeContent($content), $contents),
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => (int) ceil($total / $limit),
            ],
        ];

        // Cache the result (triggers FIFO eviction if needed)
        $this->cache->set($cacheKey, $result);

        return $result;
    }

    private function generateCacheKey(?string $keyword, ?string $type, int $page, int $limit): string
    {
        return sprintf(
            'q:%s|t:%s|p:%d|l:%d',
            $keyword ?? 'all',
            $type ?? 'all',
            $page,
            $limit
        );
    }

    private function serializeContent($content): array
    {
        return [
            'id' => $content->getId(),
            'provider_id' => $content->getProviderId(),
            'title' => $content->getTitle(),
            'type' => $content->getType()->value,
            'metrics' => $content->getMetrics(),
            'published_at' => $content->getPublishedAt()->format('Y-m-d H:i:s'),
            'score' => $content->getScore(),
            'created_at' => $content->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
