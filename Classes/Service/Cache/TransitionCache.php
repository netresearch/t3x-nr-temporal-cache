<?php

declare(strict_types=1);

/*
 * SPDX-License-Identifier: GPL-2.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 */

namespace Netresearch\TemporalCache\Service\Cache;

use TYPO3\CMS\Core\SingletonInterface;

/**
 * Request-level cache for temporal transition queries.
 *
 * This service provides in-memory caching for transition lookups within a single request,
 * preventing redundant database queries when multiple components need the same transition data.
 *
 * Performance impact:
 * - Eliminates duplicate queries in same request (2-5 queries reduced to 1)
 * - Minimal memory overhead (~100 bytes per cached entry)
 * - Cache automatically cleared at request end (no stale data risk)
 *
 * Use cases:
 * - DynamicTimingStrategy calculating cache lifetime
 * - GlobalScopingStrategy checking next transition
 * - Multiple page rendering components accessing same data
 *
 * Implementation note:
 * This is request-level caching only. Cross-request caching is handled by
 * TYPO3's native database query cache and result caching mechanisms.
 */
final class TransitionCache implements SingletonInterface
{
    /**
     * Cache storage for next transition lookups.
     *
     * Key format: "next_{currentTime}_{workspaceUid}_{languageUid}"
     * Value: int|null (transition timestamp or null if no transitions)
     *
     * @var array<string, int|null>
     */
    private array $nextTransitionCache = [];

    /**
     * Get cached next transition if available.
     *
     * @param int $currentTimestamp Reference timestamp
     * @param int $workspaceUid Workspace UID
     * @param int $languageUid Language UID
     * @return int|null Cached transition timestamp, or null if not cached
     */
    public function getNextTransition(int $currentTimestamp, int $workspaceUid, int $languageUid): ?int
    {
        $cacheKey = $this->generateNextTransitionKey($currentTimestamp, $workspaceUid, $languageUid);

        // Return cached value if exists (including null)
        if (\array_key_exists($cacheKey, $this->nextTransitionCache)) {
            return $this->nextTransitionCache[$cacheKey];
        }

        return null;
    }

    /**
     * Check if next transition is cached.
     *
     * @param int $currentTimestamp Reference timestamp
     * @param int $workspaceUid Workspace UID
     * @param int $languageUid Language UID
     * @return bool True if cached (even if value is null)
     */
    public function hasNextTransition(int $currentTimestamp, int $workspaceUid, int $languageUid): bool
    {
        $cacheKey = $this->generateNextTransitionKey($currentTimestamp, $workspaceUid, $languageUid);

        return \array_key_exists($cacheKey, $this->nextTransitionCache);
    }

    /**
     * Set cached next transition.
     *
     * @param int $currentTimestamp Reference timestamp
     * @param int $workspaceUid Workspace UID
     * @param int $languageUid Language UID
     * @param int|null $nextTransition Transition timestamp or null
     */
    public function setNextTransition(
        int $currentTimestamp,
        int $workspaceUid,
        int $languageUid,
        ?int $nextTransition
    ): void {
        $cacheKey = $this->generateNextTransitionKey($currentTimestamp, $workspaceUid, $languageUid);
        $this->nextTransitionCache[$cacheKey] = $nextTransition;
    }

    /**
     * Clear all cached transitions.
     *
     * Useful for testing or when temporal content is modified during request.
     */
    public function clear(): void
    {
        $this->nextTransitionCache = [];
    }

    /**
     * Generate cache key for next transition lookup.
     *
     * Cache key includes all parameters that affect the query result:
     * - Current timestamp (different times may have different next transitions)
     * - Workspace UID (different workspaces have different content)
     * - Language UID (different languages have different content)
     *
     * Note: We use timestamp as-is, not rounded, because transition calculations
     * depend on precise timing. In practice, multiple calls in same request
     * will use the same timestamp value.
     */
    private function generateNextTransitionKey(int $currentTimestamp, int $workspaceUid, int $languageUid): string
    {
        return \sprintf('next_%d_%d_%d', $currentTimestamp, $workspaceUid, $languageUid);
    }

    /**
     * Get cache statistics for monitoring/debugging.
     *
     * @return array{entries: int, memory: int}
     */
    public function getStats(): array
    {
        return [
            'entries' => \count($this->nextTransitionCache),
            'memory' => \strlen(\serialize($this->nextTransitionCache)),
        ];
    }
}
