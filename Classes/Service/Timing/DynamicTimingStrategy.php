<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Service\Timing;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TransitionEvent;
use Netresearch\TemporalCache\Service\Scoping\ScopingStrategyInterface;
use TYPO3\CMS\Core\Context\Context;

/**
 * Dynamic timing strategy - event-based cache lifetime calculation.
 *
 * This strategy provides Phase 1 behavior: calculate cache lifetime based on
 * the next upcoming transition, causing automatic cache expiration.
 *
 * How it works:
 * 1. On every page generation (EventListener), calculate time until next transition
 * 2. Set page cache lifetime to that duration
 * 3. When cache expires, TYPO3 regenerates page and recalculates lifetime
 * 4. Expired pages automatically get updated content
 *
 * Advantages:
 * - Automatic, no scheduler needed
 * - Content appears/disappears precisely at configured time
 * - Works with all scoping strategies
 *
 * Trade-offs:
 * - Runs on every page view (minimal overhead)
 * - Cache expires even if page not visited (slight inefficiency)
 * - Not suitable for very high traffic sites (use scheduler strategy)
 *
 * Use cases:
 * - Small to medium sites
 * - Sites with irregular traffic patterns
 * - Sites where precision timing is critical
 */
class DynamicTimingStrategy implements TimingStrategyInterface
{
    public function __construct(
        private readonly ScopingStrategyInterface $scopingStrategy,
        private readonly ExtensionConfiguration $configuration
    ) {
    }

    /**
     * {@inheritdoc}
     *
     * Dynamic strategy handles all content types by default.
     */
    public function handlesContentType(string $contentType): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * Dynamic strategy doesn't process transitions directly - it relies on
     * cache expiration and regeneration. This method is a no-op.
     */
    public function processTransition(TransitionEvent $event): void
    {
        // Dynamic strategy doesn't need to process transitions
        // Cache expiration handles everything automatically
    }

    /**
     * {@inheritdoc}
     *
     * Calculate cache lifetime based on next upcoming transition.
     *
     * Algorithm:
     * 1. Find next transition timestamp across all temporal content
     * 2. Calculate seconds until that transition
     * 3. Cap at maximum configured lifetime (default 24h)
     * 4. Return lifetime in seconds
     *
     * Example:
     * - Current time: 10:00:00
     * - Next transition: 14:30:00
     * - Cache lifetime: 16200 seconds (4.5 hours)
     * - At 14:30:00, cache expires and page regenerates
     */
    public function getCacheLifetime(Context $context, ?int $pageId = null): ?int
    {
        $currentTime = \time();

        // Delegate transition discovery to the scoping strategy so per-page/per-content
        // scoping can narrow the cache lifetime to the page currently being rendered.
        $nextTransition = $this->scopingStrategy->getNextTransition($context, $pageId);

        // No transitions? Cache for maximum lifetime
        if ($nextTransition === null) {
            return $this->configuration->getDefaultMaxLifetime();
        }

        // Calculate lifetime until next transition
        $lifetime = $nextTransition - $currentTime;

        // Ensure positive lifetime (shouldn't happen, but safety check)
        if ($lifetime <= 0) {
            return 60; // Minimum 1 minute
        }

        // Cap at maximum configured lifetime
        $maxLifetime = $this->configuration->getDefaultMaxLifetime();
        if ($lifetime > $maxLifetime) {
            return $maxLifetime;
        }

        return $lifetime;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'dynamic';
    }
}
