<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Service\Scoping;

use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepositoryInterface;
use TYPO3\CMS\Core\Context\Context;

/**
 * Global scoping strategy - flushes ALL page caches.
 *
 * This strategy provides backward compatibility with Phase 1 behavior.
 * When any temporal content transitions, it flushes the entire 'pages' cache tag,
 * causing all page caches to be invalidated.
 *
 * Use case:
 * - Maximum safety (guarantees all affected pages are cleared)
 * - Simple configuration (no complexity)
 * - Small sites where cache rebuild is fast
 *
 * Trade-off:
 * - High cache churn (all pages cleared even if only one affected)
 * - Lower cache hit rate compared to per-page or per-content strategies
 */
class GlobalScopingStrategy implements ScopingStrategyInterface
{
    public function __construct(
        private readonly TemporalContentRepositoryInterface $temporalContentRepository
    ) {
    }

    /**
     * {@inheritdoc}
     *
     * Always returns ['pages'] tag, causing all page caches to be flushed.
     */
    public function getCacheTagsToFlush(TemporalContent $content, Context $context): array
    {
        return ['pages'];
    }

    /**
     * {@inheritdoc}
     *
     * Returns the next transition across ALL temporal content in the system.
     * Global scoping flushes every page, so the page id is intentionally ignored.
     */
    public function getNextTransition(Context $context, ?int $pageId = null): ?int
    {
        $workspaceId = $context->getPropertyFromAspect('workspace', 'id', 0);
        $languageId = $context->getPropertyFromAspect('language', 'id', 0);
        \assert(\is_int($workspaceId));
        \assert(\is_int($languageId));

        return $this->temporalContentRepository->getNextTransition(
            \time(),
            $workspaceId,
            $languageId
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'global';
    }
}
