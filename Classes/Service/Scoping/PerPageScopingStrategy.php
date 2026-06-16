<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Service\Scoping;

use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepositoryInterface;
use TYPO3\CMS\Core\Context\Context;

/**
 * Per-page scoping strategy - flushes only the affected page cache.
 *
 * This strategy provides page-level granularity for cache invalidation:
 * - For pages: flushes only that page's cache
 * - For content elements: flushes only the parent page's cache
 *
 * Use case:
 * - Medium-sized sites with independent pages
 * - Content elements that don't use sys_refindex references
 * - Balance between safety and efficiency
 *
 * Trade-off:
 * - More efficient than global strategy
 * - May miss pages that reference content via CONTENT/RECORDS cObjects
 * - Use per-content strategy if content is referenced across pages
 */
class PerPageScopingStrategy implements ScopingStrategyInterface
{
    public function __construct(
        private readonly TemporalContentRepositoryInterface $temporalContentRepository
    ) {
    }

    /**
     * {@inheritdoc}
     *
     * Returns ['pageId_X'] tag where X is:
     * - The page UID for pages
     * - The parent page UID (pid) for content elements
     */
    public function getCacheTagsToFlush(TemporalContent $content, Context $context): array
    {
        if ($content->isPage()) {
            // For pages, flush the page's own cache
            return ['pageId_' . $content->uid];
        }

        // For content elements, flush the parent page cache
        return ['pageId_' . $content->pid];
    }

    /**
     * {@inheritdoc}
     *
     * Per-page scoping watches the smallest set of transitions that can change THIS page:
     * - every page transition site-wide (a page appearing/disappearing changes menus everywhere)
     * - content-element transitions on this page only
     *
     * This narrows content-driven cache churn to the page being rendered while keeping menus
     * correct. When no page id is available (e.g. CLI) it falls back to the site-wide transition.
     *
     * Note: per-page scoping does not track content embedded from other pages (CONTENT/RECORDS
     * cObjects) - use the per-content strategy for that.
     */
    public function getNextTransition(Context $context, ?int $pageId = null): ?int
    {
        $workspaceId = $context->getPropertyFromAspect('workspace', 'id', 0);
        $languageId = $context->getPropertyFromAspect('language', 'id', 0);
        \assert(\is_int($workspaceId));
        \assert(\is_int($languageId));

        $now = \time();

        if ($pageId === null) {
            return $this->temporalContentRepository->getNextTransition($now, $workspaceId, $languageId);
        }

        $candidates = \array_filter([
            $this->temporalContentRepository->getNextPageTransition($now, $workspaceId, $languageId),
            $this->temporalContentRepository->getNextContentTransitionForPage($pageId, $now, $workspaceId, $languageId),
        ], static fn (?int $value): bool => $value !== null);

        return empty($candidates) ? null : \min($candidates);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'per-page';
    }
}
