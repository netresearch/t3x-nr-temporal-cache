<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Service;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Service for harmonizing timestamps to configured time slots.
 *
 * Time slot harmonization reduces cache churn by rounding transition times
 * to predefined slots (e.g., 00:00, 06:00, 12:00, 18:00). This groups
 * multiple transitions together, reducing the number of cache flushes.
 *
 * Example:
 * - Without harmonization: transitions at 00:05, 00:15, 00:45 → 3 cache flushes
 * - With harmonization: all round to 00:00 → 1 cache flush
 *
 * Configuration:
 * - Slots: Time slots (HH:MM format, e.g., "00:00,06:00,12:00,18:00")
 * - Tolerance: Max seconds to round (e.g., 3600 = 1 hour)
 * - Auto-round: Automatically apply on save (backend integration)
 */
class HarmonizationService implements SingletonInterface
{
    /**
     * Parsed time slots in seconds since midnight.
     *
     * @var array<int>
     */
    private array $slots = [];

    public function __construct(
        private readonly ExtensionConfiguration $configuration,
        private readonly ConnectionPool $connectionPool
    ) {
        $this->initializeSlots();
    }

    /**
     * Initialize time slots from configuration.
     *
     * Parses slot configuration (HH:MM format) into seconds since midnight
     * and sorts them for efficient processing.
     */
    private function initializeSlots(): void
    {
        $slotStrings = $this->configuration->getHarmonizationSlots();
        $slots = [];

        foreach ($slotStrings as $slotString) {
            $seconds = $this->parseTimeSlot($slotString);
            if ($seconds !== null) {
                $slots[] = $seconds;
            }
        }

        \sort($slots);
        $this->slots = $slots;
    }

    /**
     * Parse time slot string (HH:MM) to seconds since midnight.
     *
     * @param string $slotString Time in HH:MM format
     * @return int|null Seconds since midnight, or null if invalid
     */
    private function parseTimeSlot(string $slotString): ?int
    {
        if (!\preg_match('/^(\d{1,2}):(\d{2})$/', \trim($slotString), $matches)) {
            return null;
        }

        $hours = (int)$matches[1];
        $minutes = (int)$matches[2];

        if ($hours > 23 || $minutes > 59) {
            return null;
        }

        return ($hours * 3600) + ($minutes * 60);
    }

    /**
     * Harmonize a timestamp to the nearest configured slot.
     *
     * Algorithm:
     * 1. Extract time of day (seconds since midnight)
     * 2. Find nearest slot within tolerance
     * 3. If found, adjust timestamp to that slot
     * 4. If no slot within tolerance, return original timestamp
     *
     * @param int $timestamp Unix timestamp to harmonize
     * @return int Harmonized timestamp (or original if no slot within tolerance)
     */
    public function harmonizeTimestamp(int $timestamp): int
    {
        if (!$this->configuration->isHarmonizationEnabled()) {
            return $timestamp;
        }

        if (empty($this->slots)) {
            return $timestamp;
        }

        // Extract time of day (seconds since midnight in local timezone)
        $dateTime = new \DateTime('@' . $timestamp);
        $timeOfDay = ((int)$dateTime->format('H') * 3600) +
                     ((int)$dateTime->format('i') * 60) +
                     ((int)$dateTime->format('s'));

        // Find nearest slot
        $nearestSlot = $this->findNearestSlot($timeOfDay);

        if ($nearestSlot === null) {
            return $timestamp;
        }

        // Calculate distance to nearest slot
        $distance = \abs($timeOfDay - $nearestSlot);

        // Check if within tolerance
        $tolerance = $this->configuration->getHarmonizationTolerance();
        if ($distance > $tolerance) {
            return $timestamp;
        }

        // Adjust timestamp to the slot
        $adjustment = $nearestSlot - $timeOfDay;
        return $timestamp + $adjustment;
    }

    /**
     * Find the nearest time slot to the given time of day.
     *
     * @param int $timeOfDay Seconds since midnight
     * @return int|null Nearest slot in seconds, or null if no slots configured
     */
    private function findNearestSlot(int $timeOfDay): ?int
    {
        if (empty($this->slots)) {
            return null;
        }

        $nearestSlot = $this->slots[0];
        $minDistance = \abs($timeOfDay - $nearestSlot);

        foreach ($this->slots as $slot) {
            $distance = \abs($timeOfDay - $slot);
            // Prefer forward-rounding when distances are equal (for scheduling clarity)
            if ($distance < $minDistance || ($distance === $minDistance && $slot > $timeOfDay)) {
                $minDistance = $distance;
                $nearestSlot = $slot;
            }
        }

        return $nearestSlot;
    }

    /**
     * Get all time slots within a date range.
     *
     * This method generates a list of all slot timestamps between start and end dates,
     * useful for timeline visualization in the backend module.
     *
     * Example:
     * - Slots: 00:00, 12:00
     * - Range: 2024-01-01 to 2024-01-03
     * - Result: [
     *     2024-01-01 00:00,
     *     2024-01-01 12:00,
     *     2024-01-02 00:00,
     *     2024-01-02 12:00,
     *     2024-01-03 00:00,
     *     2024-01-03 12:00
     *   ]
     *
     * @param int $startTimestamp Start of range (Unix timestamp)
     * @param int $endTimestamp End of range (Unix timestamp)
     * @return array<int> Array of slot timestamps in chronological order
     */
    public function getSlotsInRange(int $startTimestamp, int $endTimestamp): array
    {
        if (empty($this->slots)) {
            return [];
        }

        $slotTimestamps = [];

        // Start from beginning of start day
        $currentDate = new \DateTime('@' . $startTimestamp);
        $currentDate->setTime(0, 0, 0);

        $endDate = new \DateTime('@' . $endTimestamp);

        while ($currentDate <= $endDate) {
            $dayStart = $currentDate->getTimestamp();

            // Add all slots for this day
            foreach ($this->slots as $slotSeconds) {
                $slotTimestamp = $dayStart + $slotSeconds;

                // Only include slots within the range
                if ($slotTimestamp >= $startTimestamp && $slotTimestamp <= $endTimestamp) {
                    $slotTimestamps[] = $slotTimestamp;
                }
            }

            // Move to next day
            $currentDate->modify('+1 day');
        }

        return $slotTimestamps;
    }

    /**
     * Get the next slot timestamp after the given time.
     *
     * Useful for calculating cache lifetime: "cache until next slot".
     *
     * @param int $timestamp Reference timestamp
     * @return int|null Next slot timestamp, or null if no slots configured
     */
    public function getNextSlot(int $timestamp): ?int
    {
        if (empty($this->slots)) {
            return null;
        }

        $dateTime = new \DateTime('@' . $timestamp);
        $timeOfDay = ((int)$dateTime->format('H') * 3600) +
                     ((int)$dateTime->format('i') * 60) +
                     ((int)$dateTime->format('s'));

        // Find next slot today
        foreach ($this->slots as $slot) {
            if ($slot > $timeOfDay) {
                $dayStart = clone $dateTime;
                $dayStart->setTime(0, 0, 0);
                return $dayStart->getTimestamp() + $slot;
            }
        }

        // No slot today, return first slot tomorrow
        $tomorrow = clone $dateTime;
        $tomorrow->modify('+1 day');
        $tomorrow->setTime(0, 0, 0);
        return $tomorrow->getTimestamp() + $this->slots[0];
    }

    /**
     * Get the previous slot timestamp before the given time.
     *
     * Useful for analytics: "what was the last slot boundary?"
     *
     * @param int $timestamp Reference timestamp
     * @return int|null Previous slot timestamp, or null if no slots configured
     */
    public function getPreviousSlot(int $timestamp): ?int
    {
        if (empty($this->slots)) {
            return null;
        }

        $dateTime = new \DateTime('@' . $timestamp);
        $timeOfDay = ((int)$dateTime->format('H') * 3600) +
                     ((int)$dateTime->format('i') * 60) +
                     ((int)$dateTime->format('s'));

        // Find previous slot today (iterate backwards)
        $reversedSlots = \array_reverse($this->slots);
        foreach ($reversedSlots as $slot) {
            if ($slot < $timeOfDay) {
                $dayStart = clone $dateTime;
                $dayStart->setTime(0, 0, 0);
                return $dayStart->getTimestamp() + $slot;
            }
        }

        // No slot today, return last slot yesterday
        $yesterday = clone $dateTime;
        $yesterday->modify('-1 day');
        $yesterday->setTime(0, 0, 0);
        return $yesterday->getTimestamp() + \end($this->slots);
    }

    /**
     * Check if a timestamp is exactly on a slot boundary.
     *
     * @param int $timestamp Timestamp to check
     * @return bool True if timestamp is on a slot boundary
     */
    public function isOnSlotBoundary(int $timestamp): bool
    {
        if (empty($this->slots)) {
            return false;
        }

        $dateTime = new \DateTime('@' . $timestamp);
        $timeOfDay = ((int)$dateTime->format('H') * 3600) +
                     ((int)$dateTime->format('i') * 60) +
                     ((int)$dateTime->format('s'));

        return \in_array($timeOfDay, $this->slots, true);
    }

    /**
     * Get human-readable slot time (HH:MM format).
     *
     * @param int $slotSeconds Seconds since midnight
     * @return string Time in HH:MM format
     */
    public function formatSlot(int $slotSeconds): string
    {
        $hours = \floor($slotSeconds / 3600);
        $minutes = \floor(($slotSeconds % 3600) / 60);

        return \sprintf('%02d:%02d', $hours, $minutes);
    }

    /**
     * Get all configured slots as array of formatted strings.
     *
     * @return array<string> Array of slot times in HH:MM format
     */
    public function getFormattedSlots(): array
    {
        return \array_map(
            fn (int $slot) => $this->formatSlot($slot),
            $this->slots
        );
    }

    /**
     * Calculate potential cache reduction from harmonization.
     *
     * This method estimates how many transitions could be grouped together
     * by harmonization, useful for backend module statistics.
     *
     * @param array<int> $timestamps Array of transition timestamps
     * @return array{original: int, harmonized: int, reduction: float} Statistics
     */
    public function calculateHarmonizationImpact(array $timestamps): array
    {
        $originalCount = \count($timestamps);

        if ($originalCount === 0) {
            return [
                'original' => 0,
                'harmonized' => 0,
                'reduction' => 0.0,
            ];
        }

        // Harmonize all timestamps and count unique values
        $harmonized = \array_map(
            fn (int $ts) => $this->harmonizeTimestamp($ts),
            $timestamps
        );

        $harmonizedCount = \count(\array_unique($harmonized));

        $reduction = (($originalCount - $harmonizedCount) / $originalCount) * 100;

        return [
            'original' => $originalCount,
            'harmonized' => $harmonizedCount,
            'reduction' => \round($reduction, 1),
        ];
    }

    /**
     * Harmonize temporal content (starttime/endtime fields).
     *
     * @param \Netresearch\TemporalCache\Domain\Model\TemporalContent $content Content to harmonize
     * @param bool $dryRun If true, don't persist changes
     * @return array{success: bool, message: string, changes: array<string, array{old: int|null, new: int|null}>}
     */
    public function harmonizeContent(
        \Netresearch\TemporalCache\Domain\Model\TemporalContent $content,
        bool $dryRun = false
    ): array {
        $changes = [];
        $modified = false;

        // Harmonize starttime if set
        if ($content->starttime !== null) {
            $harmonized = $this->harmonizeTimestamp($content->starttime);
            if ($harmonized !== $content->starttime) {
                $changes['starttime'] = [
                    'old' => $content->starttime,
                    'new' => $harmonized,
                ];
                $modified = true;
            }
        }

        // Harmonize endtime if set
        if ($content->endtime !== null) {
            $harmonized = $this->harmonizeTimestamp($content->endtime);
            if ($harmonized !== $content->endtime) {
                $changes['endtime'] = [
                    'old' => $content->endtime,
                    'new' => $harmonized,
                ];
                $modified = true;
            }
        }

        if (!$modified) {
            return [
                'success' => true,
                'message' => 'No changes needed - timestamps already harmonized',
                'changes' => [],
            ];
        }

        if ($dryRun) {
            return [
                'success' => true,
                'message' => 'Dry-run: Changes would be applied',
                'changes' => $changes,
            ];
        }

        // Persist the harmonized timestamps to the database.
        $updateFields = [];
        foreach ($changes as $field => $change) {
            $updateFields[$field] = $change['new'];
        }

        try {
            $connection = $this->connectionPool->getConnectionForTable($content->tableName);
            $affectedRows = $connection->update(
                $content->tableName,
                $updateFields,
                ['uid' => $content->uid]
            );
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Failed to persist harmonized timestamps: ' . $e->getMessage(),
                'changes' => $changes,
            ];
        }

        if ($affectedRows < 1) {
            return [
                'success' => false,
                'message' => \sprintf('Record %s:%d could not be updated', $content->tableName, $content->uid),
                'changes' => $changes,
            ];
        }

        return [
            'success' => true,
            'message' => 'Content harmonized successfully',
            'changes' => $changes,
        ];
    }
}
