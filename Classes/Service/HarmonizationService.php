<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Service;

use DateTime;
use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
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
 * Configuration this service reads:
 * - Slots: Time slots (HH:MM format, e.g., "00:00,06:00,12:00,18:00")
 * - Tolerance: Max seconds to round (e.g., 3600 = 1 hour)
 *
 * Harmonization runs on demand only: through harmonizeContent() from the backend
 * module, and through HarmonizeCommand on the command line. Nothing rounds a
 * record while it is being saved - the extension registers no DataHandler hook and
 * no FormEngine integration, and this service never reads harmonization.auto_round.
 * That setting is a reporting flag: the status report and the analyze/verify
 * commands display its value, and nothing else consumes it.
 *
 * Persistence note (deliberate DataHandler bypass):
 * harmonizeContent() writes through Connection::update() instead of DataHandler.
 * Harmonization only rounds the starttime/endtime fields of records that already
 * exist; routing each one through a DataHandler datamap cycle would add per-record
 * permission checks, hooks and cache flushes to what is a bulk maintenance
 * operation. HarmonizeCommand persists its own batch the same way.
 * The trade-off is that these writes produce no sys_log and no sys_history entry,
 * so every mutation is recorded through the injected logger (table, uid, old and
 * new values) to keep it auditable.
 *
 * @internal Not covered by the public API — see Documentation/Api/Index.rst.
 */
class HarmonizationService implements SingletonInterface
{
    private const SECONDS_PER_DAY = 86400;

    /**
     * The point at which a slot is equally far away in both directions.
     */
    private const HALF_DAY = 43200;

    /**
     * Parsed time slots in seconds since midnight.
     *
     * @var array<int>
     */
    private array $slots;

    public function __construct(
        private readonly ExtensionConfiguration $configuration,
        private readonly ConnectionPool $connectionPool,
        private readonly LoggerInterface $logger = new NullLogger()
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
     * 2. Find the nearest slot on the circular day
     * 3. If it lies within the tolerance, shift the timestamp onto it
     * 4. Otherwise return the original timestamp
     *
     * Distances wrap around midnight, so the nearest slot can be the previous or
     * the next day's occurrence: with slots at 00:00 and 18:00, 23:30 harmonizes
     * forward by 30 minutes onto the next day's 00:00 rather than backwards by
     * 5.5 hours onto today's 18:00. The returned timestamp therefore need not fall
     * on the calendar day of the input.
     *
     * @param int $timestamp Unix timestamp to harmonize
     * @return int Harmonized timestamp (or original if no slot within tolerance)
     */
    public function harmonizeTimestamp(int $timestamp): int
    {
        if (!$this->configuration->isHarmonizationEnabled()) {
            return $timestamp;
        }

        if ($this->slots === []) {
            return $timestamp;
        }

        // Extract time of day (seconds since midnight). DateTime('@...') is always
        // UTC, so a day is exactly SECONDS_PER_DAY long here and the wrap-around
        // arithmetic below is not disturbed by daylight saving transitions.
        $dateTime = new DateTime('@' . $timestamp);
        $timeOfDay = ((int)$dateTime->format('H') * 3600) +
                     ((int)$dateTime->format('i') * 60) +
                     ((int)$dateTime->format('s'));

        // Signed shift onto the nearest slot, which may lie on the adjacent day.
        $offset = $this->findNearestSlotOffset($timeOfDay);

        if ($offset === null) {
            return $timestamp;
        }

        // Check if within tolerance
        $tolerance = $this->configuration->getHarmonizationTolerance();
        if (\abs($offset) > $tolerance) {
            return $timestamp;
        }

        // Adjust timestamp to the slot
        return $timestamp + $offset;
    }

    /**
     * Find the signed shift that moves a time of day onto the nearest slot.
     *
     * Distances are measured on the circular day, so a slot may be reached by
     * crossing midnight. The result lies in the range (-HALF_DAY, HALF_DAY]:
     * negative shifts the timestamp backwards, positive forwards. Where two slots
     * are equally distant the forward one wins, which at the exact half-day wrap
     * point means the next day's occurrence rather than today's.
     *
     * @param int $timeOfDay Seconds since midnight
     * @return int|null Signed shift in seconds, or null if no slots configured
     */
    private function findNearestSlotOffset(int $timeOfDay): ?int
    {
        if ($this->slots === []) {
            return null;
        }

        $nearestOffset = null;

        foreach ($this->slots as $slot) {
            $offset = $slot - $timeOfDay;

            if ($offset > self::HALF_DAY) {
                // More than half a day ahead - yesterday's occurrence is nearer.
                $offset -= self::SECONDS_PER_DAY;
            } elseif ($offset <= -self::HALF_DAY) {
                // More than half a day behind - tomorrow's occurrence is nearer.
                // At exactly half a day both directions tie, and forward wins.
                $offset += self::SECONDS_PER_DAY;
            }

            if ($nearestOffset === null) {
                $nearestOffset = $offset;
                continue;
            }

            $distance = \abs($offset);
            $nearestDistance = \abs($nearestOffset);

            // Prefer forward-rounding when distances are equal (for scheduling clarity)
            if ($distance < $nearestDistance || ($distance === $nearestDistance && $offset > 0)) {
                $nearestOffset = $offset;
            }
        }

        return $nearestOffset;
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
        if ($this->slots === []) {
            return [];
        }

        $slotTimestamps = [];

        // Start from beginning of start day
        $currentDate = new DateTime('@' . $startTimestamp);
        $currentDate->setTime(0, 0, 0);

        $endDate = new DateTime('@' . $endTimestamp);

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
        if ($this->slots === []) {
            return null;
        }

        $dateTime = new DateTime('@' . $timestamp);
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
        if ($this->slots === []) {
            return null;
        }

        $dateTime = new DateTime('@' . $timestamp);
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
        if ($this->slots === []) {
            return false;
        }

        $dateTime = new DateTime('@' . $timestamp);
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
            $this->formatSlot(...),
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
            $this->harmonizeTimestamp(...),
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
     * @param TemporalContent $content Content to harmonize
     * @param bool $dryRun If true, don't persist changes
     * @return array{success: bool, message: string, changes: array<string, array{old: int|null, new: int|null}>}
     */
    public function harmonizeContent(
        TemporalContent $content,
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
        } catch (Throwable $e) {
            // The message is surfaced verbatim in the backend AJAX response - keep it
            // free of database internals and log the detail instead.
            $this->logger->error('Failed to persist harmonized timestamps', [
                'table' => $content->tableName,
                'uid' => $content->uid,
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to persist harmonized timestamps',
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

        // This write bypasses DataHandler (see class docblock), so it leaves no
        // sys_log/sys_history trail - record it here instead.
        $this->logger->info('Harmonized temporal timestamps', [
            'table' => $content->tableName,
            'uid' => $content->uid,
            'changes' => $changes,
        ]);

        return [
            'success' => true,
            'message' => 'Content harmonized successfully',
            'changes' => $changes,
        ];
    }
}
