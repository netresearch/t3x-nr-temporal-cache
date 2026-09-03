<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Service\Scoping;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Service\SelectsNamedStrategy;
use TYPO3\CMS\Core\Context\Context;

/**
 * Factory for selecting the appropriate scoping strategy based on extension configuration.
 *
 * This factory acts as a proxy that delegates to the configured strategy.
 * It implements the ScopingStrategyInterface so it can be injected directly.
 *
 * @internal Not covered by the public API — see Documentation/Api/Index.rst.
 */
class ScopingStrategyFactory implements ScopingStrategyInterface
{
    use SelectsNamedStrategy;

    private readonly ScopingStrategyInterface $activeStrategy;

    /**
     * @param iterable<array-key, ScopingStrategyInterface> $strategies All services tagged 'nr_temporal_cache.scoping_strategy'
     * @param ExtensionConfiguration $extensionConfiguration Extension configuration
     */
    public function __construct(
        iterable $strategies,
        ExtensionConfiguration $extensionConfiguration
    ) {
        // The fallback is the first tagged service, i.e. the highest tag priority;
        // GlobalScopingStrategy carries priority 100 to stay the default.
        $this->activeStrategy = $this->selectNamedStrategy(
            $strategies,
            $extensionConfiguration->getScopingStrategy(),
            'No scoping strategies registered'
        );
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
