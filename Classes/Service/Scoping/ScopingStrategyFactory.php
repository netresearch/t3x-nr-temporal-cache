<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Service\Scoping;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use RuntimeException;
use TYPO3\CMS\Core\Context\Context;

/**
 * Factory for selecting the appropriate scoping strategy based on extension configuration.
 *
 * This factory acts as a proxy that delegates to the configured strategy.
 * It implements the ScopingStrategyInterface so it can be injected directly.
 */
class ScopingStrategyFactory implements ScopingStrategyInterface
{
    private readonly ScopingStrategyInterface $activeStrategy;

    /**
     * @param iterable<array-key, ScopingStrategyInterface> $strategies All services tagged 'nr_temporal_cache.scoping_strategy'
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
     * @param iterable<array-key, ScopingStrategyInterface> $strategies
     * @return ScopingStrategyInterface
     */
    private function selectStrategy(iterable $strategies): ScopingStrategyInterface
    {
        $configuredStrategy = $this->extensionConfiguration->getScopingStrategy();
        $firstStrategy = null;

        // Find matching strategy by name (more reliable for testing with mocks)
        foreach ($strategies as $strategy) {
            $firstStrategy ??= $strategy;

            if ($strategy->getName() === $configuredStrategy) {
                return $strategy;
            }
        }

        // Fallback to the first tagged strategy: highest tag priority, GlobalScopingStrategy
        // carries priority 100 in Services.yaml to keep the backward compatible default.
        return $firstStrategy ?? throw new RuntimeException('No scoping strategies registered');
    }

    /**
     * Delegate to active strategy.
     */
    public function getCacheTagsToFlush(TemporalContent $content, Context $context): array
    {
        return $this->activeStrategy->getCacheTagsToFlush($content, $context);
    }

    /**
     * Delegate to active strategy.
     */
    public function getNextTransition(Context $context, ?int $pageId = null): ?int
    {
        return $this->activeStrategy->getNextTransition($context, $pageId);
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
    public function getActiveStrategy(): ScopingStrategyInterface
    {
        return $this->activeStrategy;
    }
}
