<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Domain\Repository;

use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Model\TransitionEvent;

/**
 * Interface for temporal content repository operations.
 *
 * This interface defines the contract for finding and managing temporal content
 * (pages and content elements with starttime/endtime fields).
 */
interface TemporalContentRepositoryInterface
{
    /**
     * Find all pages and content elements with temporal fields.
     *
     * @param int $workspaceUid Workspace UID (0 = live workspace)
     * @param int $languageUid Language UID (-1 = all languages)
     * @return array<TemporalContent>
     */
    public function findAllWithTemporalFields(int $workspaceUid = 0, int $languageUid = -1): array;

    /**
     * Find all transitions in a specific time range.
     *
     * @param int $startTimestamp Start of time range
     * @param int $endTimestamp End of time range
     * @param int $workspaceUid Workspace UID
     * @param int $languageUid Language UID
     * @return array<TransitionEvent>
     */
    public function findTransitionsInRange(
        int $startTimestamp,
        int $endTimestamp,
        int $workspaceUid = 0,
        int $languageUid = 0
    ): array;

    /**
     * Get the next upcoming transition after the given timestamp.
     *
     * @param int $currentTimestamp Reference timestamp
     * @param int $workspaceUid Workspace UID
     * @param int $languageUid Language UID
     * @return int|null Timestamp of next transition, or null if none
     */
    public function getNextTransition(
        int $currentTimestamp,
        int $workspaceUid = 0,
        int $languageUid = 0
    ): ?int;

    /**
     * Get the next page-record transition (pages table only).
     *
     * Page transitions affect menus on every page, so they are never scoped to a single page.
     *
     * @return int|null Timestamp of the next page transition, or null if none
     */
    public function getNextPageTransition(
        int $currentTimestamp,
        int $workspaceUid = 0,
        int $languageUid = 0
    ): ?int;

    /**
     * Get the next content-element transition for a single page (content tables, scoped by pid).
     *
     * @param int $pageId The page the rendered output belongs to
     * @return int|null Timestamp of the next content transition on that page, or null if none
     */
    public function getNextContentTransitionForPage(
        int $pageId,
        int $currentTimestamp,
        int $workspaceUid = 0,
        int $languageUid = 0
    ): ?int;

    /**
     * Count transitions per day in a date range.
     *
     * @param int $startTimestamp Start of range
     * @param int $endTimestamp End of range
     * @param int $workspaceUid Workspace UID
     * @return array<string, int> Date (Y-m-d) => count
     */
    public function countTransitionsPerDay(
        int $startTimestamp,
        int $endTimestamp,
        int $workspaceUid = 0
    ): array;

    /**
     * Get statistics about temporal content.
     *
     * @param int $workspaceUid Workspace UID
     * @return array{total: int, pages: int, content: int, withStart: int, withEnd: int, withBoth: int}
     */
    public function getStatistics(int $workspaceUid = 0): array;

    /**
     * Find temporal content by page ID.
     *
     * @param int $pageId Page UID
     * @param int $workspaceUid Workspace UID
     * @param int $languageUid Language UID
     * @return array<TemporalContent>
     */
    public function findByPageId(
        int $pageId,
        int $workspaceUid = 0,
        int $languageUid = 0
    ): array;

    /**
     * Find content element by UID.
     *
     * @param int $uid Content element UID
     * @param string $tableName Table name ('pages' or 'tt_content')
     * @param int $workspaceUid Workspace UID
     * @return TemporalContent|null
     */
    public function findByUid(int $uid, string $tableName = 'tt_content', int $workspaceUid = 0): ?TemporalContent;
}
