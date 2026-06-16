<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Service\Timing;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TransitionEvent;
use Netresearch\TemporalCache\Service\Scoping\ScopingStrategyInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Context\Context;

/**
 * Scheduler timing strategy - background processing of transitions.
 *
 * This strategy decouples cache invalidation from page generation:
 * 1. Page caches live indefinitely (no expiration)
 * 2. Scheduler task runs periodically (e.g., every minute)
 * 3. Task finds transitions since last run
 * 4. Task flushes affected caches using scoping strategy
 *
 * Advantages:
 * - Zero overhead on page generation (no cache lifetime calculation)
 * - Predictable performance (invalidation happens in background)
 * - Suitable for high-traffic sites
 * - Caches only invalidated when transitions actually occur
 *
 * Trade-offs:
 * - Requires scheduler configuration
 * - Small delay possible (up to scheduler interval)
 * - Content might appear/disappear slightly late
 *
 * Use cases:
 * - High-traffic production sites
 * - Sites where performance is critical
 * - Sites with frequent temporal transitions
 * - Sites where 1-minute delay is acceptable
 *
 * Configuration:
 * - scheduler_interval: How often task runs (default 60 seconds)
 * - Works with all scoping strategies
 */
class SchedulerTimingStrategy implements TimingStrategyInterface
{
    public function __construct(
        private readonly ScopingStrategyInterface $scopingStrategy,
        private readonly CacheManager $cacheManager,
        private readonly Context $context,
        private readonly LoggerInterface $logger,
        private readonly ExtensionConfiguration $configuration
    ) {
    }

    /**
     * {@inheritdoc}
     *
     * Scheduler strategy handles all content types by default.
     */
    public function handlesContentType(string $contentType): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * Process a transition by flushing affected caches.
     *
     * This method is called by the scheduler task for each transition
     * that occurred since the last run.
     *
     * Algorithm:
     * 1. Use scoping strategy to determine which caches to flush
     * 2. Flush those cache tags
     * 3. Log the invalidation for debugging
     *
     * Example:
     * - Transition: Content #123 starts at 14:00
     * - Scoping: per-content finds pages [5, 10, 15]
     * - Result: Flush pageId_5, pageId_10, pageId_15
     */
    public function processTransition(TransitionEvent $event): void
    {
        try {
            // Get cache tags from scoping strategy
            $cacheTags = $this->scopingStrategy->getCacheTagsToFlush(
                $event->content,
                $this->context
            );

            // Flush page caches for those tags
            $pageCache = $this->cacheManager->getCache('pages');
            foreach ($cacheTags as $tag) {
                $pageCache->flushByTag($tag);
            }

            // Log successful invalidation if debug logging is enabled
            if ($this->isDebugLoggingEnabled()) {
                $this->logTransitionProcessing($event, $cacheTags);
            }
        } catch (\Exception $e) {
            // Log error but don't throw - scheduler should continue processing other transitions
            $this->logError($event, $e);
        }
    }

    /**
     * {@inheritdoc}
     *
     * Scheduler strategy returns null - caches live indefinitely.
     *
     * Cache invalidation is handled by the scheduler task calling
     * processTransition() when transitions occur.
     */
    public function getCacheLifetime(Context $context, ?int $pageId = null): ?int
    {
        // Return null = cache lives indefinitely
        // Scheduler task will flush when needed
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'scheduler';
    }

    /**
     * Log transition processing for debugging.
     *
     * @param TransitionEvent $event The processed transition
     * @param array<string> $cacheTags The cache tags that were flushed
     */
    private function logTransitionProcessing(TransitionEvent $event, array $cacheTags): void
    {
        $this->logger->info(
            'Processed temporal transition',
            [
                'event' => $event->getLogMessage(),
                'flushed_tags' => $cacheTags,
                'strategy' => $this->scopingStrategy->getName(),
            ]
        );
    }

    /**
     * Log error during transition processing.
     *
     * @param TransitionEvent $event The transition that failed
     * @param \Exception $exception The error that occurred
     */
    private function logError(TransitionEvent $event, \Exception $exception): void
    {
        $this->logger->error(
            'Failed to process temporal transition',
            [
                'event' => $event->getLogMessage(),
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]
        );
    }

    /**
     * Check if debug logging is enabled.
     *
     * @return bool True if debug logging is enabled
     */
    private function isDebugLoggingEnabled(): bool
    {
        try {
            return $this->configuration->isDebugLoggingEnabled();
        } catch (\Exception $e) {
            return false;
        }
    }
}
