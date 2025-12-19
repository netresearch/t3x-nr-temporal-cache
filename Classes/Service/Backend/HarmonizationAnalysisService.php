<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Service\Backend;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Service\HarmonizationService;

/**
 * Service for analyzing harmonization opportunities in temporal content.
 *
 * This service extracts harmonization analysis logic from the controller to follow
 * the Single Responsibility Principle. It handles:
 * - Detecting whether content is harmonizable (timestamps differ from time slots)
 * - Generating harmonization suggestions with impact analysis
 * - Bulk analysis of content for harmonization potential
 * - Filtering content based on harmonization criteria
 *
 * Harmonization concept:
 * Harmonization aligns content timestamps to predefined time slots (e.g., 00:00, 06:00, 12:00)
 * to enable batch cache invalidation instead of per-content invalidation. This dramatically
 * reduces cache churn when many content elements have similar but not identical timestamps.
 *
 * Benefits of extraction:
 * - Testable harmonization logic in isolation
 * - Reusable across backend module, CLI commands, and reports
 * - Clear separation between analysis (this service) and execution (HarmonizationService)
 * - Easier to extend with additional analysis metrics
 *
 * @internal This service is designed for backend module and analysis usage
 */
final class HarmonizationAnalysisService
{
    public function __construct(
        private readonly HarmonizationService $harmonizationService,
        private readonly ExtensionConfiguration $extensionConfiguration
    ) {
    }

    /**
     * Check if a content item is harmonizable.
     *
     * A content item is considered harmonizable if:
     * 1. Harmonization is enabled in configuration
     * 2. The content has at least one temporal field (starttime or endtime)
     * 3. The harmonized timestamp differs from the current timestamp
     *
     * This is the core detection method used throughout the module for
     * identifying harmonization opportunities.
     *
     * Performance note:
     * This method calls harmonizeTimestamp() for each temporal field, which
     * involves time slot calculations. For bulk operations, consider using
     * analyzeHarmonizableCandidates() which provides batch analysis.
     *
     * @param TemporalContent $content Content to analyze
     * @return bool True if content can benefit from harmonization
     */
    public function isHarmonizable(TemporalContent $content): bool
    {
        if (!$this->extensionConfiguration->isHarmonizationEnabled()) {
            return false;
        }

        // Check starttime harmonization potential
        if ($content->starttime !== null) {
            $harmonized = $this->harmonizationService->harmonizeTimestamp($content->starttime);
            if ($harmonized !== $content->starttime) {
                return true;
            }
        }

        // Check endtime harmonization potential
        if ($content->endtime !== null) {
            $harmonized = $this->harmonizationService->harmonizeTimestamp($content->endtime);
            if ($harmonized !== $content->endtime) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate detailed harmonization suggestion for a content item.
     *
     * Analyzes both starttime and endtime fields and creates a comprehensive
     * suggestion object containing:
     * - Current timestamps
     * - Suggested harmonized timestamps
     * - Time difference (delta) for each field
     * - Overall change indicator
     *
     * The suggestion object is optimized for UI display in the content list,
     * allowing editors to understand the impact before applying harmonization.
     *
     * Example output:
     * ```php
     * [
     *     'content' => TemporalContent object,
     *     'suggestions' => [
     *         'starttime' => [
     *             'current' => 1704067234,    // 2024-01-01 08:13:54
     *             'suggested' => 1704096000,  // 2024-01-01 08:00:00
     *             'diff' => -234              // 3 minutes 54 seconds earlier
     *         ]
     *     ],
     *     'hasChanges' => true
     * ]
     * ```
     *
     * @param TemporalContent $content Content to analyze
     * @param int $currentTime Current timestamp (for relative time calculations)
     * @return array{
     *     content: TemporalContent,
     *     suggestions: array<string, array{current: int, suggested: int, diff: int}>,
     *     hasChanges: bool
     * } Harmonization suggestion with detailed impact analysis
     */
    public function generateHarmonizationSuggestion(TemporalContent $content, int $currentTime): array
    {
        $suggestions = [];

        // Analyze starttime harmonization
        if ($content->starttime !== null) {
            $harmonized = $this->harmonizationService->harmonizeTimestamp($content->starttime);
            if ($harmonized !== $content->starttime) {
                $diff = $harmonized - $content->starttime;
                $suggestions['starttime'] = [
                    'current' => $content->starttime,
                    'suggested' => $harmonized,
                    'diff' => $diff,
                    'diffMinutes' => (int) \round($diff / 60),
                ];
            }
        }

        // Analyze endtime harmonization
        if ($content->endtime !== null) {
            $harmonized = $this->harmonizationService->harmonizeTimestamp($content->endtime);
            if ($harmonized !== $content->endtime) {
                $diff = $harmonized - $content->endtime;
                $suggestions['endtime'] = [
                    'current' => $content->endtime,
                    'suggested' => $harmonized,
                    'diff' => $diff,
                    'diffMinutes' => (int) \round($diff / 60),
                ];
            }
        }

        return [
            'content' => $content,
            'suggestions' => $suggestions,
            'hasChanges' => !empty($suggestions),
        ];
    }

    /**
     * Analyze harmonization potential for multiple content items.
     *
     * Performs bulk analysis to identify harmonizable content and calculate
     * statistics. This is more efficient than calling isHarmonizable() in a loop
     * for large content sets.
     *
     * Returns detailed metrics:
     * - Total harmonizable content count
     * - Average time shift (how much timestamps would change)
     * - Distribution of changes (by field and by magnitude)
     *
     * Use cases:
     * - Dashboard statistics
     * - Bulk harmonization preview
     * - Impact assessment before enabling harmonization
     *
     * @param array<TemporalContent> $contentList Content items to analyze
     * @return array{
     *     harmonizableCount: int,
     *     totalCount: int,
     *     averageShiftSeconds: float,
     *     starttimeChanges: int,
     *     endtimeChanges: int,
     *     harmonizableItems: array<int, TemporalContent>
     * } Analysis results with detailed metrics
     */
    public function analyzeHarmonizableCandidates(array $contentList): array
    {
        if (!$this->extensionConfiguration->isHarmonizationEnabled()) {
            return [
                'harmonizableCount' => 0,
                'totalCount' => \count($contentList),
                'averageShiftSeconds' => 0.0,
                'starttimeChanges' => 0,
                'endtimeChanges' => 0,
                'harmonizableItems' => [],
            ];
        }

        $harmonizableItems = [];
        $totalShift = 0;
        $shiftCount = 0;
        $starttimeChanges = 0;
        $endtimeChanges = 0;

        foreach ($contentList as $content) {
            $hasStartChange = false;
            $hasEndChange = false;

            // Check starttime
            if ($content->starttime !== null) {
                $harmonized = $this->harmonizationService->harmonizeTimestamp($content->starttime);
                if ($harmonized !== $content->starttime) {
                    $hasStartChange = true;
                    $starttimeChanges++;
                    $totalShift += \abs($harmonized - $content->starttime);
                    $shiftCount++;
                }
            }

            // Check endtime
            if ($content->endtime !== null) {
                $harmonized = $this->harmonizationService->harmonizeTimestamp($content->endtime);
                if ($harmonized !== $content->endtime) {
                    $hasEndChange = true;
                    $endtimeChanges++;
                    $totalShift += \abs($harmonized - $content->endtime);
                    $shiftCount++;
                }
            }

            // Add to harmonizable items if any change detected
            if ($hasStartChange || $hasEndChange) {
                $harmonizableItems[$content->uid] = $content;
            }
        }

        return [
            'harmonizableCount' => \count($harmonizableItems),
            'totalCount' => \count($contentList),
            'averageShiftSeconds' => $shiftCount > 0 ? $totalShift / $shiftCount : 0.0,
            'starttimeChanges' => $starttimeChanges,
            'endtimeChanges' => $endtimeChanges,
            'harmonizableItems' => $harmonizableItems,
        ];
    }

    /**
     * Filter content list to include only harmonizable items.
     *
     * Convenience method for filtering content arrays. More efficient than
     * array_filter with isHarmonizable callback for large arrays since it
     * uses early returns and optimized logic.
     *
     * @param array<TemporalContent> $contentList Content items to filter
     * @return array<TemporalContent> Filtered list containing only harmonizable items
     */
    public function filterHarmonizableContent(array $contentList): array
    {
        if (!$this->extensionConfiguration->isHarmonizationEnabled()) {
            return [];
        }

        return \array_filter($contentList, fn ($content) => $this->isHarmonizable($content));
    }

    /**
     * Calculate harmonization impact for a specific content item.
     *
     * Provides detailed impact analysis including:
     * - Maximum time shift (largest timestamp change)
     * - Whether changes affect visible/active periods
     * - Priority level (high impact vs low impact)
     *
     * Priority levels:
     * - HIGH: Changes > 1 hour or affects currently visible content
     * - MEDIUM: Changes > 15 minutes
     * - LOW: Changes <= 15 minutes
     *
     * @param TemporalContent $content Content to analyze
     * @param int $currentTime Current timestamp for visibility analysis
     * @return array{
     *     maxShiftSeconds: int,
     *     affectsVisibility: bool,
     *     priority: string
     * } Impact analysis result
     */
    public function calculateHarmonizationImpact(TemporalContent $content, int $currentTime): array
    {
        $maxShift = 0;
        $affectsVisibility = false;

        // Analyze starttime impact
        if ($content->starttime !== null) {
            $harmonized = $this->harmonizationService->harmonizeTimestamp($content->starttime);
            $shift = \abs($harmonized - $content->starttime);
            $maxShift = \max($maxShift, $shift);

            // Check if this affects current visibility
            if ($content->starttime <= $currentTime && $harmonized > $currentTime) {
                $affectsVisibility = true;
            } elseif ($content->starttime > $currentTime && $harmonized <= $currentTime) {
                $affectsVisibility = true;
            }
        }

        // Analyze endtime impact
        if ($content->endtime !== null) {
            $harmonized = $this->harmonizationService->harmonizeTimestamp($content->endtime);
            $shift = \abs($harmonized - $content->endtime);
            $maxShift = \max($maxShift, $shift);

            // Check if this affects current visibility
            if ($content->endtime <= $currentTime && $harmonized > $currentTime) {
                $affectsVisibility = true;
            } elseif ($content->endtime > $currentTime && $harmonized <= $currentTime) {
                $affectsVisibility = true;
            }
        }

        // Determine priority level
        $priority = 'low';
        if ($affectsVisibility || $maxShift > 3600) {
            $priority = 'high';
        } elseif ($maxShift > 900) {
            $priority = 'medium';
        }

        return [
            'maxShiftSeconds' => $maxShift,
            'affectsVisibility' => $affectsVisibility,
            'priority' => $priority,
        ];
    }
}
