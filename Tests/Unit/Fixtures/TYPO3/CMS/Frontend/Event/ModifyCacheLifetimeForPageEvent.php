<?php

declare(strict_types=1);

namespace TYPO3\CMS\Frontend\Event;

use TYPO3\CMS\Core\Context\Context;

/**
 * Test stub for TYPO3 13 ModifyCacheLifetimeForPageEvent.
 *
 * This stub allows unit tests to run without requiring full TYPO3 installation.
 * Mimics the TYPO3 13 event interface with complete constructor signature.
 *
 * TYPO3 13 moved this event from TYPO3\CMS\Core\Cache\Event to TYPO3\CMS\Frontend\Event
 * and changed the constructor from 1 parameter to 5 parameters.
 *
 * @internal For testing purposes only
 */
class ModifyCacheLifetimeForPageEvent
{
    /**
     * @param int $cacheLifetime Cache lifetime in seconds
     * @param int $pageId Page ID
     * @param array<string, mixed> $pageRecord Page record
     * @param array<string, mixed> $renderingInstructions TypoScript rendering instructions
     * @param Context $context TYPO3 Context object
     */
    public function __construct(
        private int $cacheLifetime,
        private readonly int $pageId = 0,
        private readonly array $pageRecord = [],
        private readonly array $renderingInstructions = [],
        private readonly ?Context $context = null
    ) {
    }

    /**
     * Get cache lifetime in seconds.
     */
    public function getCacheLifetime(): int
    {
        return $this->cacheLifetime;
    }

    /**
     * Set cache lifetime in seconds.
     */
    public function setCacheLifetime(int $cacheLifetime): void
    {
        $this->cacheLifetime = $cacheLifetime;
    }

    /**
     * Get page ID.
     */
    public function getPageId(): int
    {
        return $this->pageId;
    }

    /**
     * Get page record.
     *
     * @return array<string, mixed>
     */
    public function getPageRecord(): array
    {
        return $this->pageRecord;
    }

    /**
     * Get rendering instructions (TypoScript configuration).
     *
     * @return array<string, mixed>
     */
    public function getRenderingInstructions(): array
    {
        return $this->renderingInstructions;
    }

    /**
     * Get TYPO3 Context object.
     */
    public function getContext(): ?Context
    {
        return $this->context;
    }
}
