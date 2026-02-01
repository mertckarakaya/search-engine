<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use Predis\Client;
use Psr\Log\LoggerInterface;

/**
 * Custom Redis FIFO cache implementation
 * Stores maximum 10 unique search queries
 * Auto-evicts oldest when limit is reached
 */
final class RedisCacheAdapter
{
    private const MAX_QUERIES = 10;
    private const CACHE_PREFIX = 'search:';
    private const QUERY_LIST_KEY = 'search:queries';
    private const TTL = 3600; // 1 hour

    public function __construct(
        private readonly Client $redis,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function get(string $key): ?array
    {
        $cacheKey = self::CACHE_PREFIX . $this->hashKey($key);
        $data = $this->redis->get($cacheKey);

        if ($data === null) {
            $this->logger?->info('Cache miss', ['key' => $key]);
            return null;
        }

        $this->logger?->info('Cache hit', ['key' => $key]);
        return json_decode($data, true);
    }

    public function set(string $key, array $data): void
    {
        $cacheKey = self::CACHE_PREFIX . $this->hashKey($key);
        $timestamp = time();

        // Check current query count
        $currentCount = $this->redis->zcard(self::QUERY_LIST_KEY);

        // If we've reached the limit, remove the oldest entry
        if ($currentCount >= self::MAX_QUERIES) {
            $this->evictOldest();
        }

        // Store the data
        $this->redis->setex($cacheKey, self::TTL, json_encode($data));

        // Add to sorted set (using timestamp as score for FIFO)
        $this->redis->zadd(self::QUERY_LIST_KEY, [$cacheKey => $timestamp]);

        $this->logger?->info('Cache set', [
            'key' => $key,
            'size' => strlen(json_encode($data)),
            'current_count' => $currentCount + 1,
        ]);
    }

    private function evictOldest(): void
    {
        // Get the oldest entry (lowest score/timestamp)
        $oldest = $this->redis->zrange(self::QUERY_LIST_KEY, 0, 0);

        if (!empty($oldest)) {
            $oldestKey = $oldest[0];

            // Remove from cache and sorted set
            $this->redis->del([$oldestKey]);
            $this->redis->zrem(self::QUERY_LIST_KEY, $oldestKey);

            $this->logger?->info('Evicted oldest cache entry', [
                'key' => $oldestKey,
            ]);
        }
    }

    private function hashKey(string $key): string
    {
        return md5($key);
    }

    public function clear(): void
    {
        $keys = $this->redis->zrange(self::QUERY_LIST_KEY, 0, -1);

        if (!empty($keys)) {
            $this->redis->del($keys);
        }

        $this->redis->del([self::QUERY_LIST_KEY]);

        $this->logger?->info('Cache cleared');
    }

    public function getStats(): array
    {
        $count = $this->redis->zcard(self::QUERY_LIST_KEY);
        $keys = $this->redis->zrange(self::QUERY_LIST_KEY, 0, -1, ['WITHSCORES' => true]);

        return [
            'total_queries' => $count,
            'max_queries' => self::MAX_QUERIES,
            'queries' => $keys,
        ];
    }
}
