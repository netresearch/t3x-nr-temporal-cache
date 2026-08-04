<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Service\Backend;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Model\TransitionEvent;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepositoryInterface;
use Netresearch\TemporalCache\Service\HarmonizationService;

/**
 * Service for calculating statistics and metrics for the Temporal Cache backend module.
 *
 * This service extracts all statistics-related logic from the controller to follow
 * the Single Responsibility Principle. It handles:
 * - Dashboard statistics calculation (total counts, active/future content)
 * - Timeline generation for transition visualization
 * - Configuration summary for dashboard display
 * - Harmonization potential analysis
 *
 * Benefits of extraction:
 * - Testable in isolation without controller dependencies
 * - Reusable across different contexts (CLI, API, reports)
 * - Clear separation between presentation logic (controller) and business logic (service)
 * - Reduced controller complexity (18 methods → lighter, more focused)
 *
 * @internal This service is designed for backend module usage
 */
final class TemporalCacheStatisticsService
{
    public function __construct(
        private readonly TemporalContentRepositoryInterface $contentRepository,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly HarmonizationService $harmonizationService
    ) {
    }

    /**
     * Calculate comprehensive statistics for dashboard display.
     *
     * Returns detailed metrics including:
     * - Total content counts (pages, content elements)
     * - Visibility status (active, scheduled, expired)
     * - Transition counts (upcoming changes in next 30 days)
     * - Harmonization potential (content that could benefit from time slot alignment)
     *
     * Performance considerations:
     * - Loads all temporal content once, then applies multiple filters
     * - For large installations (>1000 temporal records), consider caching
     * - Uses efficient array_filter operations for categorization
     *
     * @param int $currentTime Reference timestamp (usually current time)
     * @param int $workspaceUid Workspace UID (default 0 = live workspace)
     * @param int $languageUid Language UID (default -1 = all languages)
     * @return array{
     *     totalCount: int,
     *     pageCount: int,
     *     contentCount: int,
     *     activeCount: int,
     *     futureCount: int,
     *     transitionsNext30Days: int,
     *     transitionsPerDay: int,
     *     harmonizableCandidates: int
     * } Statistics array with comprehensive metrics
     */
    public function calculateStatistics(
        int $currentTime,
        int $workspaceUid = 0,
        int $languageUid = -1
    ): array {
        $allContent = $this->contentRepository->findAllWithTemporalFields($workspaceUid, $languageUid);
        $transitions = $this->contentRepository->findTransitionsInRange(
            $currentTime,
            $currentTime + 86400 * 30,
            $workspaceUid,
            $languageUid
        );

        // Count by content type
        $pageCount = \count(\array_filter($allContent, fn (TemporalContent $c): bool => $c->isPage()));
        $contentCount = \count(\array_filter($allContent, fn (TemporalContent $c): bool => $c->isContent()));

        // Count by visibility status
        $activeCount = \count(\array_filter($allContent, fn (TemporalContent $c): bool => $c->isVisible($currentTime)));
        $futureCount = \count(\array_filter(
            $allContent,
            fn (TemporalContent $c): bool => $c->starttime !== null && $c->starttime > $currentTime
        ));

        // Calculate harmonization potential
        $harmonizableCandidates = 0;
        if ($this->extensionConfiguration->isHarmonizationEnabled()) {
            foreach ($allContent as $content) {
                if ($content->starttime !== null) {
                    $harmonized = $this->harmonizationService->harmonizeTimestamp($content->starttime);
                    if ($harmonized !== $content->starttime) {
                        $harmonizableCandidates++;
                    }
                }
            }
        }

        // Get transition statistics
        $startDate = \date('Y-m-d', $currentTime);
        $endDate = \date('Y-m-d', $currentTime + 86400 * 30);
        $startTimestamp = \strtotime($startDate);
        $endTimestamp = \strtotime($endDate);
        \assert($startTimestamp !== false && $endTimestamp !== false);
        $transitionsPerDay = $this->contentRepository->countTransitionsPerDay(
            $startTimestamp,
            $endTimestamp,
            $workspaceUid
        );

        return [
            'totalCount' => \count($allContent),
            'pageCount' => $pageCount,
            'contentCount' => $contentCount,
            'activeCount' => $activeCount,
            'futureCount' => $futureCount,
            'transitionsNext30Days' => \count($transitions),
            'transitionsPerDay' => \count($transitionsPerDay),
            'harmonizableCandidates' => $harmonizableCandidates,
        ];
    }

    /**
     * Build timeline data structure for next N days.
     *
     * Creates a day-by-day breakdown of upcoming transitions (content becoming
     * visible or hidden). Used for timeline visualization in the dashboard.
     *
     * Timeline structure:
     * - Each day contains all transitions occurring on that day
     * - Transitions are grouped by date (Y-m-d format)
     * - Includes both start and end transitions
     * - Sorted chronologically
     *
     * Use cases:
     * - Dashboard timeline visualization
     * - Editorial calendar display
     * - Planning and preview of upcoming content changes
     *
     * @param int $currentTime Reference timestamp (usually current time)
     * @param int $daysAhead Number of days to include in timeline (default 7)
     * @param int $workspaceUid Workspace UID (default 0 = live workspace)
     * @param int $languageUid Language UID (default 0 = default language)
     * @return array<array{date: string, timestamp: int, transitions: array<TransitionEvent>}> Timeline data grouped by day
     */
    public function buildTimeline(
        int $currentTime,
        int $daysAhead = 7,
        int $workspaceUid = 0,
        int $languageUid = 0
    ): array {
        $timeline = [];
        $endTime = $currentTime + (86400 * $daysAhead);

        $transitions = $this->contentRepository->findTransitionsInRange(
            $currentTime,
            $endTime,
            $workspaceUid,
            $languageUid
        );

        // Group transitions by day
        foreach ($transitions as $transition) {
            $dayKey = \date('Y-m-d', $transition->timestamp);
            if (!isset($timeline[$dayKey])) {
                $dayTimestamp = \strtotime($dayKey . ' 00:00:00');
                \assert($dayTimestamp !== false);
                $timeline[$dayKey] = [
                    'date' => $dayKey,
                    'timestamp' => $dayTimestamp,
                    'transitions' => [],
                ];
            }

            $timeline[$dayKey]['transitions'][] = $transition;
        }

        // Return as indexed array (sorted by date)
        return \array_values($timeline);
    }

    /**
     * Get configuration summary for dashboard display.
     *
     * Extracts the current extension configuration and formats it for
     * dashboard presentation. This provides editors with quick insight
     * into how the temporal cache is configured.
     *
     * Configuration aspects included:
     * - Scoping strategy (global, per-page, per-content)
     * - Timing strategy (dynamic, scheduler, hybrid)
     * - Harmonization status (enabled/disabled)
     * - Reference index usage
     * - Debug logging status
     *
     * @return array{
     *     scopingStrategy: string,
     *     timingStrategy: string,
     *     harmonizationEnabled: bool,
     *     useRefindex: bool,
     *     debugLogging: bool
     * } Configuration summary
     */
    public function getConfigurationSummary(): array
    {
        return [
            'scopingStrategy' => $this->extensionConfiguration->getScopingStrategy(),
            'timingStrategy' => $this->extensionConfiguration->getTimingStrategy(),
            'harmonizationEnabled' => $this->extensionConfiguration->isHarmonizationEnabled(),
            'useRefindex' => $this->extensionConfiguration->useRefindex(),
            'debugLogging' => $this->extensionConfiguration->isDebugLoggingEnabled(),
        ];
    }

    /**
     * Calculate average transitions per day over a period.
     *
     * Provides a metric for understanding temporal content activity levels.
     * Useful for determining optimal configuration (e.g., whether to enable
     * harmonization or scheduler-based timing).
     *
     * @param int $startTime Period start timestamp
     * @param int $endTime Period end timestamp
     * @param int $workspaceUid Workspace UID (default 0 = live workspace)
     * @return float Average transitions per day
     */
    public function calculateAverageTransitionsPerDay(
        int $startTime,
        int $endTime,
        int $workspaceUid = 0
    ): float {
        $transitionsPerDay = $this->contentRepository->countTransitionsPerDay(
            $startTime,
            $endTime,
            $workspaceUid
        );

        if ($transitionsPerDay === []) {
            return 0.0;
        }

        $totalTransitions = \array_sum($transitionsPerDay);
        $dayCount = \count($transitionsPerDay);

        return $totalTransitions / $dayCount;
    }

    /**
     * Get peak transition day information.
     *
     * Identifies the day with the most transitions in a given period,
     * useful for capacity planning and understanding content patterns.
     *
     * @param int $startTime Period start timestamp
     * @param int $endTime Period end timestamp
     * @param int $workspaceUid Workspace UID (default 0 = live workspace)
     * @return array{date: string, count: int}|null Peak day info, or null if no transitions
     */
    public function getPeakTransitionDay(
        int $startTime,
        int $endTime,
        int $workspaceUid = 0
    ): ?array {
        $transitionsPerDay = $this->contentRepository->countTransitionsPerDay(
            $startTime,
            $endTime,
            $workspaceUid
        );

        if ($transitionsPerDay === []) {
            return null;
        }

        \arsort($transitionsPerDay);
        $peakDate = \array_key_first($transitionsPerDay);
        $peakCount = $transitionsPerDay[$peakDate];

        return [
            'date' => $peakDate,
            'count' => $peakCount,
        ];
    }
}
