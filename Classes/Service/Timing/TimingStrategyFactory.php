<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Service\Timing;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TransitionEvent;
use RuntimeException;
use TYPO3\CMS\Core\Context\Context;

/**
 * Factory for selecting the appropriate timing strategy based on extension configuration.
 *
 * This factory acts as a proxy that delegates to the configured strategy.
 * It implements the TimingStrategyInterface so it can be injected directly.
 */
class TimingStrategyFactory implements TimingStrategyInterface
{
    private readonly TimingStrategyInterface $activeStrategy;

    /**
     * @param iterable<array-key, TimingStrategyInterface> $strategies All services tagged 'nr_temporal_cache.timing_strategy'
     * @param ExtensionConfiguration $extensionConfiguration Extension configuration
     */
    public function __construct(
        iterable $strategies,
        private readonly ExtensionConfiguration $extensionConfiguration
    ) {
        $this->activeStrategy = $this->selectStrategy($strategies);
    }

    /**
     * Select active strategy based on extension configuration.
     *
     * @param iterable<array-key, TimingStrategyInterface> $strategies
     * @return TimingStrategyInterface
     */
    private function selectStrategy(iterable $strategies): TimingStrategyInterface
    {
        $configuredStrategy = $this->extensionConfiguration->getTimingStrategy();
        $firstStrategy = null;

        // Find matching strategy by name (more reliable for testing with mocks)
        foreach ($strategies as $strategy) {
            $firstStrategy ??= $strategy;

            if ($strategy->getName() === $configuredStrategy) {
                return $strategy;
            }
        }

        // Fallback to the first tagged strategy: highest tag priority, DynamicTimingStrategy
        // carries priority 100 in Services.yaml to keep the backward compatible default.
        return $firstStrategy ?? throw new RuntimeException('No timing strategies registered');
    }

    /**
     * Delegate to active strategy.
     */
    public function handlesContentType(string $contentType): bool
    {
        return $this->activeStrategy->handlesContentType($contentType);
    }

    /**
     * Delegate to active strategy.
     */
    public function processTransition(TransitionEvent $event): void
    {
        $this->activeStrategy->processTransition($event);
    }

    /**
     * Delegate to active strategy.
     */
    public function getCacheLifetime(Context $context, ?int $pageId = null): ?int
    {
        return $this->activeStrategy->getCacheLifetime($context, $pageId);
    }

    /**
     * Return active strategy name for debugging.
     */
    public function getName(): string
    {
        return $this->activeStrategy->getName();
    }

    /**
     * Get the active strategy instance for testing.
     *
     * @internal
     */
    public function getActiveStrategy(): TimingStrategyInterface
    {
        return $this->activeStrategy;
    }
}
