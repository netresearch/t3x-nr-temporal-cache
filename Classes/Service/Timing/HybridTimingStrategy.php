<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Service\Timing;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TransitionEvent;
use TYPO3\CMS\Core\Context\Context;

/**
 * Hybrid timing strategy - combines dynamic and scheduler strategies.
 *
 * This strategy provides the best of both worlds by delegating to different
 * strategies based on content type:
 * - Pages → Dynamic strategy (event-based, precise timing)
 * - Content → Scheduler strategy (background processing, efficiency)
 *
 * Rationale:
 * - Page transitions are typically rare and important (precise timing needed)
 * - Content transitions are frequent (scheduler efficiency needed)
 * - This combination optimizes for both precision and performance
 *
 * Configuration:
 * hybrid:
 *   pages: 'dynamic'      # Pages use dynamic strategy
 *   content: 'scheduler'  # Content uses scheduler strategy
 *
 * Advantages:
 * - Flexible per-content-type configuration
 * - Optimize different content types differently
 * - Balance precision and performance
 *
 * Use cases:
 * - Large sites with mixed requirements
 * - Sites with many content elements but few page transitions
 * - Sites needing precision for pages but efficiency for content
 */
class HybridTimingStrategy implements TimingStrategyInterface
{
    /**
     * Timing rules: maps content type to strategy name.
     *
     * @var array{pages: string, content: string}
     */
    private array $timingRules;

    public function __construct(
        private readonly TimingStrategyInterface $dynamicStrategy,
        private readonly TimingStrategyInterface $schedulerStrategy,
        private readonly ExtensionConfiguration $configuration
    ) {
        $this->timingRules = $this->configuration->getTimingRules();
    }

    /**
     * {@inheritdoc}
     *
     * Hybrid strategy handles all content types, delegating to specific strategies.
     */
    public function handlesContentType(string $contentType): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * Delegate to appropriate strategy based on content type.
     *
     * Algorithm:
     * 1. Determine content type (page or content)
     * 2. Look up configured strategy for that type
     * 3. Delegate processTransition() to that strategy
     */
    public function processTransition(TransitionEvent $event): void
    {
        $strategy = $this->getStrategyForContentType($event->content->getContentType());
        $strategy->processTransition($event);
    }

    /**
     * {@inheritdoc}
     *
     * Delegate to appropriate strategy based on current page context.
     *
     * This method is called during page generation to determine cache lifetime.
     * We need to determine if the current page has temporal content and which
     * strategy should handle it.
     *
     * Algorithm:
     * 1. Assume we're generating a page (most common case)
     * 2. Look up configured strategy for 'page' type
     * 3. Delegate getCacheLifetime() to that strategy
     *
     * Note: This is called during page generation, so we can't efficiently
     * determine if specific content elements on the page use different strategies.
     * We use the 'pages' rule as the default for cache lifetime calculation.
     */
    public function getCacheLifetime(Context $context, ?int $pageId = null): ?int
    {
        // Use the strategy configured for pages (most common case)
        $strategy = $this->getStrategyForContentType('page');
        return $strategy->getCacheLifetime($context, $pageId);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'hybrid';
    }

    /**
     * Get the appropriate timing strategy for a content type.
     *
     * @param string $contentType Content type ('page' or 'content')
     * @return TimingStrategyInterface The strategy to use
     */
    private function getStrategyForContentType(string $contentType): TimingStrategyInterface
    {
        $strategyName = $this->timingRules[$contentType] ?? 'dynamic';

        return match ($strategyName) {
            'scheduler' => $this->schedulerStrategy,
            'dynamic' => $this->dynamicStrategy,
            default => $this->dynamicStrategy,
        };
    }

    /**
     * Get the strategy name for a content type (for debugging).
     *
     * @param string $contentType Content type ('page' or 'content')
     * @return string Strategy name
     */
    public function getStrategyNameForContentType(string $contentType): string
    {
        return $this->timingRules[$contentType] ?? 'dynamic';
    }

    /**
     * Get all timing rules for debugging and backend module display.
     *
     * @return array{pages: string, content: string} Timing rules
     */
    public function getTimingRules(): array
    {
        return $this->timingRules;
    }
}
