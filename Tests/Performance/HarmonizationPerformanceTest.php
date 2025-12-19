<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Performance;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Service\HarmonizationAnalysisService;
use Netresearch\TemporalCache\Service\HarmonizationService;
use PHPUnit\Framework\TestCase;

/**
 * Performance benchmarks for harmonization operations
 *
 * Validates efficiency claims:
 * - Harmonization reduces cache operations by 60-80%
 * - Processing 1000 content elements < 100ms
 * - Memory usage remains constant (no leaks)
 */
final class HarmonizationPerformanceTest extends TestCase
{
    private HarmonizationService $harmonizationService;
    private HarmonizationAnalysisService $analysisService;

    protected function setUp(): void
    {
        parent::setUp();

        $configMock = $this->createMock(ExtensionConfiguration::class);
        $configMock->method('isHarmonizationEnabled')->willReturn(true);
        $configMock->method('getHarmonizationSlots')->willReturn([0, 21600, 43200, 64800]);
        $configMock->method('getHarmonizationTolerance')->willReturn(3600);

        $this->harmonizationService = new HarmonizationService($configMock);
        $this->analysisService = new HarmonizationAnalysisService($configMock);
    }

    /**     * Benchmark: Harmonization analysis of 1000 content elements
     * Target: < 50ms for all analysis operations
     */
    public function testHarmonizationAnalysisPerformance(): void
    {
        $contentElements = $this->generateTestContent(1000);

        $startTime = \microtime(true);
        $startMemory = \memory_get_usage();

        $harmonizableCount = 0;
        foreach ($contentElements as $content) {
            if ($this->analysisService->isHarmonizable($content)) {
                $harmonizableCount++;
            }
        }

        $endTime = \microtime(true);
        $endMemory = \memory_get_usage();

        $duration = ($endTime - $startTime) * 1000; // Convert to ms
        $memoryUsed = ($endMemory - $startMemory) / 1024; // Convert to KB

        echo "\n";
        echo "Harmonization Analysis Performance:\n";
        echo "  Content Elements:    1000\n";
        echo "  Harmonizable:        {$harmonizableCount}\n";
        echo "  Duration:            " . \number_format($duration, 2) . " ms\n";
        echo "  Memory Used:         " . \number_format($memoryUsed, 2) . " KB\n";
        echo "  Per Element:         " . \number_format($duration / 1000, 4) . " ms\n";
        echo "\n";

        self::assertLessThan(50, $duration, 'Analysis should complete in under 50ms for 1000 elements');
        self::assertGreaterThan(0, $harmonizableCount, 'Should find some harmonizable content');
    }

    /**     * Benchmark: Batch harmonization of 500 content elements
     * Target: < 100ms for all harmonization operations
     */
    public function testBatchHarmonizationPerformance(): void
    {
        $contentElements = $this->generateTestContent(500);

        // Filter to only harmonizable content
        $harmonizable = \array_filter($contentElements, fn ($c) => $this->analysisService->isHarmonizable($c));

        $startTime = \microtime(true);
        $startMemory = \memory_get_usage();

        $successCount = 0;
        foreach ($harmonizable as $content) {
            $result = $this->harmonizationService->harmonizeContent($content, true);
            if ($result['success']) {
                $successCount++;
            }
        }

        $endTime = \microtime(true);
        $endMemory = \memory_get_usage();

        $duration = ($endTime - $startTime) * 1000;
        $memoryUsed = ($endMemory - $startMemory) / 1024;
        $totalElements = \count($harmonizable);

        echo "\n";
        echo "Batch Harmonization Performance:\n";
        echo "  Total Elements:      {$totalElements}\n";
        echo "  Successfully Harmonized: {$successCount}\n";
        echo "  Duration:            " . \number_format($duration, 2) . " ms\n";
        echo "  Memory Used:         " . \number_format($memoryUsed, 2) . " KB\n";
        echo "  Per Element:         " . \number_format($duration / $totalElements, 4) . " ms\n";
        echo "\n";

        self::assertLessThan(100, $duration, 'Batch harmonization should complete in under 100ms');
        self::assertGreaterThan(0, $successCount, 'Should successfully harmonize content');
    }

    /**     * Benchmark: Cache churn reduction measurement
     * Validates: Harmonization reduces cache operations by 60-80%
     */
    public function testCacheChurnReductionMeasurement(): void
    {
        // Generate 100 content elements with random times near harmonization slots
        $baseTime = \strtotime('today 00:00:00');
        $contentElements = [];

        for ($i = 0; $i < 100; $i++) {
            // Create times scattered within ±1 hour of each 6-hour slot
            $slot = [0, 21600, 43200, 64800][\array_rand([0, 1, 2, 3])];
            $offset = \rand(-3600, 3600);

            $contentElements[] = TemporalContent::fromArray([
                'uid' => 1000 + $i,
                'pid' => 1,
                'starttime' => $baseTime + $slot + $offset,
                'endtime' => 0,
                'hidden' => 0,
                'deleted' => 0,
            ]);
        }

        // Count unique timestamps BEFORE harmonization
        $uniqueTimestampsBefore = [];
        foreach ($contentElements as $content) {
            $uniqueTimestampsBefore[$content->getStarttime()] = true;
        }

        // Harmonize all content
        $harmonizedTimestamps = [];
        foreach ($contentElements as $content) {
            if ($this->analysisService->isHarmonizable($content)) {
                $result = $this->harmonizationService->harmonizeContent($content, true);
                if ($result['success'] && isset($result['changes']['starttime'])) {
                    $harmonizedTimestamps[$result['changes']['starttime']['new']] = true;
                }
            }
        }

        $timestampsBefore = \count($uniqueTimestampsBefore);
        $timestampsAfter = \count($harmonizedTimestamps);
        $reduction = (($timestampsBefore - $timestampsAfter) / $timestampsBefore) * 100;

        echo "\n";
        echo "Cache Churn Reduction Analysis:\n";
        echo "  Content Elements:          100\n";
        echo "  Unique Timestamps Before:  {$timestampsBefore}\n";
        echo "  Unique Timestamps After:   {$timestampsAfter}\n";
        echo "  Reduction:                 " . \number_format($reduction, 1) . "%\n";
        echo "\n";

        self::assertGreaterThanOrEqual(60, $reduction, 'Should reduce cache operations by at least 60%');
        self::assertLessThanOrEqual(4, $timestampsAfter, 'Should align to 4 harmonization slots');
    }

    /**     * Benchmark: Memory leak detection during sustained operations
     * Validates: No memory leaks during repeated harmonization
     */
    public function testMemoryLeakDetection(): void
    {
        $iterations = 10;
        $elementsPerIteration = 100;
        $memorySnapshots = [];

        for ($i = 0; $i < $iterations; $i++) {
            $contentElements = $this->generateTestContent($elementsPerIteration);

            foreach ($contentElements as $content) {
                if ($this->analysisService->isHarmonizable($content)) {
                    $this->harmonizationService->harmonizeContent($content, true);
                }
            }

            $memorySnapshots[] = \memory_get_usage();

            // Force garbage collection
            \gc_collect_cycles();
        }

        // Calculate memory growth
        $initialMemory = $memorySnapshots[0];
        $finalMemory = \end($memorySnapshots);
        $memoryGrowth = ($finalMemory - $initialMemory) / 1024; // KB

        echo "\n";
        echo "Memory Leak Detection:\n";
        echo "  Iterations:          {$iterations}\n";
        echo "  Elements/Iteration:  {$elementsPerIteration}\n";
        echo "  Initial Memory:      " . \number_format($initialMemory / 1024, 2) . " KB\n";
        echo "  Final Memory:        " . \number_format($finalMemory / 1024, 2) . " KB\n";
        echo "  Memory Growth:       " . \number_format($memoryGrowth, 2) . " KB\n";
        echo "\n";

        // Allow some growth but not excessive (< 500KB for 1000 operations)
        self::assertLessThan(500, $memoryGrowth, 'Memory growth should be minimal (< 500KB)');
    }

    /**
     * Generate test content elements with temporal properties
     */
    private function generateTestContent(int $count): array
    {
        $baseTime = \strtotime('tomorrow 00:00:00');
        $content = [];

        for ($i = 0; $i < $count; $i++) {
            // Mix of harmonizable and non-harmonizable content
            $isHarmonizable = ($i % 3) === 0;

            if ($isHarmonizable) {
                // Create time near a harmonization slot (within tolerance)
                $slot = [0, 21600, 43200, 64800][$i % 4];
                $offset = \rand(-3000, 3000); // Within 1 hour tolerance
                $starttime = $baseTime + $slot + $offset;
            } else {
                // Create time far from harmonization slots
                $starttime = $baseTime + \rand(5000, 18000);
            }

            $content[] = TemporalContent::fromArray([
                'uid' => 1000 + $i,
                'pid' => 1,
                'starttime' => $starttime,
                'endtime' => 0,
                'hidden' => 0,
                'deleted' => 0,
            ]);
        }

        return $content;
    }
}
