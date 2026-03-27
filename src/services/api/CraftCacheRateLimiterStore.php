<?php

namespace acclaro\translations\services\api;

use Craft;
use Spatie\GuzzleRateLimiterMiddleware\Store;
use yii\caching\CacheInterface;

/**
 * A shared rate-limiter store backed by Craft's cache component.
 *
 * Unlike the default InMemoryStore, this store survives across PHP processes
 * (web workers, queue runners, CLI), so the 3-req/s cap is truly global
 * rather than per-process.
 *
 * The Craft cache backend should be a shared store (Redis, Memcached, DB).
 * File-based cache only works on single-server setups.
 *
 * An optional $cache argument can be injected (useful for testing without
 * a running Craft application).
 */
class CraftCacheRateLimiterStore implements Store
{
    /** Cache key under which the timestamp list is stored. */
    private string $cacheKey;

    /**
     * How long (seconds) the cache entry lives as a safety TTL.
     * Entries are pruned on every write anyway; this just prevents orphans.
     */
    private int $ttl = 10;

    /** Optional injected cache — if null, Craft::$app->cache is used. */
    private ?object $cache;

    public function __construct(string $cacheKey = 'acclaro_api_rate_limiter', ?object $cache = null)
    {
        $this->cacheKey = $cacheKey;
        $this->cache    = $cache;
    }

    private function getCache(): object
    {
        return $this->cache ?? Craft::$app->cache;
    }

    public function get(): array
    {
        return $this->getCache()->get($this->cacheKey) ?: [];
    }

    /**
     * Push a new timestamp and prune entries outside the rate-limit window.
     *
     * Timestamps are in milliseconds (as produced by SleepDeferrer::getCurrentTime).
     * The perSecond window is 1 000 ms. We keep a 2 000 ms buffer to ensure
     * the RateLimiter's own filter always has enough history to calculate delays.
     */
    public function push(int $timestamp, int $limit): void
    {
        $cutoff = $timestamp - 2000;

        $timestamps = array_values(array_filter(
            $this->get(),
            fn(int $ts) => $ts >= $cutoff
        ));

        $timestamps[] = $timestamp;

        $this->getCache()->set($this->cacheKey, $timestamps, $this->ttl);
    }
}
