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
 * The split is per rule, not per call: processTransition() picks the one strategy
 * that owns the transition's content type, while getCacheLifetime() consults both
 * rules and takes the earliest lifetime, because a rendered page depends on the
 * page record and on the content on it alike.
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
     * @var array{page: string, content: string}
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
     * Ask every strategy the rules resolve to and return the earliest lifetime.
     *
     * A rendered page depends on the page record and on the content elements
     * placed on it, so both rules have a say in how long its cache may live.
     * Consulting only the page rule lets a `content = dynamic` configuration
     * hand out a cache that outlives the next content transition.
     *
     * Algorithm:
     * 1. Resolve the strategy for each rule ('page' and 'content')
     * 2. Ask each distinct strategy for its lifetime
     * 3. Return the smallest non-null answer, or null if all are null
     *
     * The minimum is the safe direction: a cache that expires before the next
     * transition is merely regenerated once too often, while one that expires
     * after it serves stale content. A rule set to 'scheduler' contributes null
     * (that strategy flushes in the background), so a hybrid configuration with
     * both rules on 'scheduler' still leaves the page cache untouched.
     *
     * Note what the rules do and do not select: they decide WHICH strategies are
     * consulted, not WHICH transitions count. The transitions themselves come
     * from the scoping strategy, which reports the next transition across pages
     * and content together without a type filter. Splitting the lifetime by
     * content type would need that filter in the scoping/repository layer.
     */
    public function getCacheLifetime(Context $context, ?int $pageId = null): ?int
    {
        $pageStrategy = $this->getStrategyForContentType('page');
        $contentStrategy = $this->getStrategyForContentType('content');

        $lifetime = $pageStrategy->getCacheLifetime($context, $pageId);

        // Both rules commonly resolve to one strategy - query it once, so the
        // transition lookups it runs during page generation are not doubled.
        if ($contentStrategy !== $pageStrategy) {
            $contentLifetime = $contentStrategy->getCacheLifetime($context, $pageId);

            if ($contentLifetime !== null && ($lifetime === null || $contentLifetime < $lifetime)) {
                $lifetime = $contentLifetime;
            }
        }

        return $lifetime;
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
     * @return array{page: string, content: string} Timing rules
     */
    public function getTimingRules(): array
    {
        return $this->timingRules;
    }
}
