<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\EventListener;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Service\Scoping\ScopingStrategyInterface;
use Netresearch\TemporalCache\Service\Timing\TimingStrategyInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Frontend\Event\ModifyCacheLifetimeForPageEvent;

/**
 * Event listener that dynamically adjusts cache lifetime based on temporal content dependencies.
 *
 * Addresses TYPO3 Forge Issue #14277: Menus and content with starttime/endtime don't update
 * automatically when time passes. This listener delegates to configurable strategies:
 *
 * - Scoping Strategy: Determines WHICH caches to invalidate (global/per-page/per-content)
 * - Timing Strategy: Determines WHEN to check transitions (dynamic/scheduler/hybrid)
 *
 * V1.0 Refactored Solution: Strategy pattern for flexible configuration.
 * Maintains backward compatibility with Phase 1 (default = global scoping + dynamic timing).
 *
 * @see https://forge.typo3.org/issues/14277
 */
final class TemporalCacheLifetime
{
    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly ScopingStrategyInterface $scopingStrategy,
        private readonly TimingStrategyInterface $timingStrategy,
        private readonly Context $context,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Modify page cache lifetime to expire at next temporal content transition.
     *
     * Delegates to timing strategy which may:
     * - Return cache lifetime (dynamic strategy)
     * - Return null (scheduler strategy - cache lives indefinitely)
     * - Conditionally choose based on content type (hybrid strategy)
     *
     * Respects TYPO3's cache configuration hierarchy:
     * 1. TypoScript config.cache_period (site-wide setting)
     * 2. Extension's default_max_lifetime (fallback)
     * 3. TYPO3's default 86400 (24 hours, final fallback)
     */
    public function __invoke(ModifyCacheLifetimeForPageEvent $event): void
    {
        try {
            $lifetime = $this->timingStrategy->getCacheLifetime($this->context);

            if ($lifetime !== null) {
                // Respect TYPO3's cache configuration hierarchy
                $renderingInstructions = $event->getRenderingInstructions();
                $maxLifetime = $this->determineMaxLifetime($renderingInstructions);
                $cappedLifetime = \min($lifetime, $maxLifetime);

                $event->setCacheLifetime($cappedLifetime);

                if ($this->extensionConfiguration->isDebugLoggingEnabled()) {
                    $this->logger->debug(
                        'Temporal cache lifetime set',
                        [
                            'lifetime' => $cappedLifetime,
                            'uncapped_lifetime' => $lifetime,
                            'max_lifetime' => $maxLifetime,
                            'max_from_typoscript' => $renderingInstructions['cache_period'] ?? null,
                            'max_from_extension_config' => $this->extensionConfiguration->getDefaultMaxLifetime(),
                            'timing_strategy' => $this->timingStrategy->getName(),
                            'scoping_strategy' => $this->scopingStrategy->getName(),
                        ]
                    );
                }
            }
        } catch (\Throwable $e) {
            // Fail gracefully - don't break page rendering on strategy errors
            $this->logger->error(
                'Temporal cache lifetime calculation failed',
                [
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'timing_strategy' => $this->timingStrategy->getName(),
                ]
            );
        }
    }

    /**
     * Determine maximum cache lifetime from TYPO3 configuration hierarchy.
     *
     * Priority:
     * 1. TypoScript config.cache_period (if configured)
     * 2. Extension setting default_max_lifetime (fallback)
     * 3. 86400 seconds / 24 hours (TYPO3 default, final fallback)
     *
     * @param array<string, mixed> $renderingInstructions TypoScript rendering instructions
     * @return int Maximum cache lifetime in seconds
     */
    private function determineMaxLifetime(array $renderingInstructions): int
    {
        // 1. Try TypoScript config.cache_period (site-wide configuration)
        if (isset($renderingInstructions['cache_period'])) {
            $cachePeriod = $renderingInstructions['cache_period'];
            \assert(\is_int($cachePeriod) || \is_numeric($cachePeriod));
            $cachePeriodInt = (int)$cachePeriod;
            if ($cachePeriodInt > 0) {
                return $cachePeriodInt;
            }
        }

        // 2. Fall back to extension configuration
        $extensionMaxLifetime = $this->extensionConfiguration->getDefaultMaxLifetime();
        if ($extensionMaxLifetime > 0) {
            return $extensionMaxLifetime;
        }

        // 3. Final fallback to TYPO3's default (24 hours)
        return 86400;
    }

    /**
     * Get scoping strategy for testing and debugging.
     *
     * @internal
     */
    public function getScopingStrategy(): ScopingStrategyInterface
    {
        return $this->scopingStrategy;
    }

    /**
     * Get timing strategy for testing and debugging.
     *
     * @internal
     */
    public function getTimingStrategy(): TimingStrategyInterface
    {
        return $this->timingStrategy;
    }
}
