<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Service\Timing;

use Netresearch\TemporalCache\Domain\Model\TransitionEvent;
use TYPO3\CMS\Core\Context\Context;

/**
 * Interface for timing strategies that determine WHEN to check for transitions.
 *
 * Strategy Pattern: Allows switching between dynamic (event-based), scheduler, and hybrid.
 */
interface TimingStrategyInterface
{
    /**
     * Should this strategy handle the given content type?
     *
     * @param string $contentType 'page' or 'content'
     */
    public function handlesContentType(string $contentType): bool;

    /**
     * Process temporal transition (for scheduler-based strategies).
     *
     * This method is called by the scheduler task when a transition occurs.
     *
     * @param TransitionEvent $event The transition that occurred
     */
    public function processTransition(TransitionEvent $event): void;

    /**
     * Get cache lifetime for event-based strategies.
     *
     * This method is called by the EventListener on every page generation.
     * Returns null if this strategy doesn't modify cache lifetime.
     *
     * @param Context $context TYPO3 context
     * @param int|null $pageId The page currently being rendered (enables per-page scoping)
     * @return int|null Seconds until next transition, or null
     */
    public function getCacheLifetime(Context $context, ?int $pageId = null): ?int;

    /**
     * Get strategy name for logging and debugging.
     */
    public function getName(): string;
}
