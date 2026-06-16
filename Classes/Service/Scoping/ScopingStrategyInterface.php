<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Service\Scoping;

use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use TYPO3\CMS\Core\Context\Context;

/**
 * Interface for scoping strategies that determine which caches to invalidate.
 *
 * Strategy Pattern: Allows switching between global, per-page, and per-content scoping.
 */
interface ScopingStrategyInterface
{
    /**
     * Get cache tags to flush when temporal content transitions.
     *
     * @param TemporalContent $content The content that transitioned
     * @param Context $context TYPO3 context (workspace, language)
     * @return array<string> Array of cache tags to flush
     */
    public function getCacheTagsToFlush(TemporalContent $content, Context $context): array;

    /**
     * Get next temporal transition timestamp for cache lifetime calculation.
     *
     * The optional page id lets a strategy scope the lifetime to the page currently being
     * rendered (e.g. per-page scoping only watches content on that page plus all page
     * transitions). When null, the strategy returns a site-wide transition.
     *
     * @param Context $context TYPO3 context
     * @param int|null $pageId The page currently being rendered, or null for a site-wide value
     * @return int|null Timestamp of next transition or null if none
     */
    public function getNextTransition(Context $context, ?int $pageId = null): ?int;

    /**
     * Get strategy name for logging and debugging.
     */
    public function getName(): string;
}
