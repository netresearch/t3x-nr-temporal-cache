<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Service\Scoping;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use TYPO3\CMS\Core\Context\Context;

/**
 * Factory for selecting the appropriate scoping strategy based on extension configuration.
 *
 * This factory acts as a proxy that delegates to the configured strategy.
 * It implements the ScopingStrategyInterface so it can be injected directly.
 */
class ScopingStrategyFactory implements ScopingStrategyInterface
{
    private ScopingStrategyInterface $activeStrategy;

    /**
     * @param array<ScopingStrategyInterface> $strategies All available strategies
     * @param ExtensionConfiguration $extensionConfiguration Extension configuration
     */
    public function __construct(
        array $strategies,
        private readonly ExtensionConfiguration $extensionConfiguration
    ) {
        $this->activeStrategy = $this->selectStrategy($strategies);
    }

    /**
     * Select active strategy based on extension configuration.
     *
     * @param array<ScopingStrategyInterface> $strategies
     * @return ScopingStrategyInterface
     */
    private function selectStrategy(array $strategies): ScopingStrategyInterface
    {
        $configuredStrategy = $this->extensionConfiguration->getScopingStrategy();

        // Find matching strategy by name (more reliable for testing with mocks)
        foreach ($strategies as $strategy) {
            if ($strategy->getName() === $configuredStrategy) {
                return $strategy;
            }
        }

        // Fallback to first strategy (should be GlobalScopingStrategy for backward compat)
        return $strategies[0] ?? throw new \RuntimeException('No scoping strategies registered');
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
