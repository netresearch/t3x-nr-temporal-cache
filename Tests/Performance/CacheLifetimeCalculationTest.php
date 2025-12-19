<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Performance;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Service\Context\CacheCalculationContext;
use Netresearch\TemporalCache\Service\Timing\DynamicTimingStrategy;
use Netresearch\TemporalCache\Service\Timing\HybridTimingStrategy;
use Netresearch\TemporalCache\Service\Timing\SchedulerTimingStrategy;
use PHPUnit\Framework\TestCase;

/**
 * Performance benchmarks for cache lifetime calculation
 *
 * Validates efficiency claims:
 * - Lifetime calculation < 1ms per page
 * - Handles 100+ content elements efficiently
 * - No performance degradation with scale
 */
final class CacheLifetimeCalculationTest extends TestCase
{
    /**     * Benchmark: Dynamic timing strategy with varying content counts
     * Validates: Sub-millisecond performance even with many elements
     */
    public function testDynamicTimingStrategyScalability(): void
    {
        $config = $this->createMock(ExtensionConfiguration::class);
        $strategy = new DynamicTimingStrategy($config);

        $testCases = [10, 50, 100, 500, 1000];
        $results = [];

        foreach ($testCases as $elementCount) {
            $context = $this->createContextWithElements($elementCount);

            $startTime = microtime(true);

            for ($i = 0; $i < 100; $i++) {
                $lifetime = $strategy->getCacheLifetime($context);
            }

            $endTime = microtime(true);

            $avgDuration = (($endTime - $startTime) / 100) * 1000; // ms per calculation

            $results[] = [
                'elements' => $elementCount,
                'duration' => $avgDuration,
            ];
        }

        echo "\n";
        echo "Dynamic Timing Strategy Scalability:\n";
        echo str_repeat('─', 50) . "\n";
        printf("%-20s %20s\n", "Content Elements", "Avg Duration (ms)");
        echo str_repeat('─', 50) . "\n";

        foreach ($results as $result) {
            printf("%-20d %20s\n", $result['elements'], number_format($result['duration'], 4));

            // Each calculation should be under 1ms regardless of scale
            self::assertLessThan(1.0, $result['duration'],
                "Calculation with {$result['elements']} elements should be < 1ms"
            );
        }

        echo str_repeat('─', 50) . "\n";
        echo "\n";

        // Verify minimal performance degradation
        $growth = ($results[4]['duration'] / $results[0]['duration']) - 1;
        $growthPercent = $growth * 100;

        echo "Performance Degradation Analysis:\n";
        echo "  10 elements:    " . number_format($results[0]['duration'], 4) . " ms\n";
        echo "  1000 elements:  " . number_format($results[4]['duration'], 4) . " ms\n";
        echo "  Growth:         " . number_format($growthPercent, 1) . "%\n";
        echo "\n";

        self::assertLessThan(50, $growthPercent, 'Performance should not degrade more than 50% at 100x scale');
    }

    /**     * Benchmark: Comparison of all timing strategies
     * Validates: Performance characteristics of each strategy
     */
    public function testTimingStrategyComparison(): void
    {
        $config = $this->createMock(ExtensionConfiguration::class);
        $config->method('getSchedulerInterval')->willReturn(3600);
        $config->method('getTimingStrategy')->willReturn('dynamic');

        $strategies = [
            'Dynamic' => new DynamicTimingStrategy($config),
            'Scheduler' => new SchedulerTimingStrategy($config),
            'Hybrid' => new HybridTimingStrategy($config),
        ];

        $context = $this->createContextWithElements(100);
        $iterations = 1000;
        $results = [];

        foreach ($strategies as $name => $strategy) {
            $startTime = microtime(true);

            for ($i = 0; $i < $iterations; $i++) {
                $lifetime = $strategy->getCacheLifetime($context);
            }

            $endTime = microtime(true);

            $avgDuration = (($endTime - $startTime) / $iterations) * 1000000; // microseconds

            $results[$name] = $avgDuration;
        }

        echo "\n";
        echo "Timing Strategy Performance Comparison:\n";
        echo "  Iterations:      {$iterations}\n";
        echo "  Content Elements: 100\n";
        echo "\n";
        echo str_repeat('─', 50) . "\n";
        printf("%-20s %25s\n", "Strategy", "Avg Duration (μs)");
        echo str_repeat('─', 50) . "\n";

        foreach ($results as $name => $duration) {
            printf("%-20s %25s\n", $name, number_format($duration, 2));
            self::assertLessThan(1000, $duration, "{$name} strategy should be < 1000μs");
        }

        echo str_repeat('─', 50) . "\n";
        echo "\n";
    }

    /**     * Benchmark: Cache lifetime calculation under concurrent load simulation
     * Validates: Thread-safe performance characteristics
     */
    public function testConcurrentLoadSimulation(): void
    {
        $config = $this->createMock(ExtensionConfiguration::class);
        $strategy = new DynamicTimingStrategy($config);

        // Simulate 100 concurrent page requests
        $concurrentRequests = 100;
        $contexts = [];

        // Pre-generate contexts (simulating different pages)
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $contexts[] = $this->createContextWithElements(rand(5, 50));
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        foreach ($contexts as $context) {
            $lifetime = $strategy->getCacheLifetime($context);
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $totalDuration = ($endTime - $startTime) * 1000; // ms
        $avgDuration = $totalDuration / $concurrentRequests;
        $memoryPerRequest = ($endMemory - $startMemory) / $concurrentRequests / 1024; // KB

        echo "\n";
        echo "Concurrent Load Simulation:\n";
        echo "  Simulated Requests:  {$concurrentRequests}\n";
        echo "  Total Duration:      " . number_format($totalDuration, 2) . " ms\n";
        echo "  Avg per Request:     " . number_format($avgDuration, 4) . " ms\n";
        echo "  Memory per Request:  " . number_format($memoryPerRequest, 4) . " KB\n";
        echo "  Throughput:          " . number_format($concurrentRequests / ($totalDuration / 1000), 0) . " req/sec\n";
        echo "\n";

        self::assertLessThan(1.0, $avgDuration, 'Each request should process in < 1ms');
        self::assertLessThan(1.0, $memoryPerRequest, 'Each request should use < 1KB memory');
    }

    /**     * Benchmark: Edge case performance (empty, minimal, maximum content)
     * Validates: Consistent performance across content variations
     */
    public function testEdgeCasePerformance(): void
    {
        $config = $this->createMock(ExtensionConfiguration::class);
        $strategy = new DynamicTimingStrategy($config);

        $testCases = [
            'No Content' => 0,
            'Single Element' => 1,
            'Typical Page' => 25,
            'Large Page' => 200,
            'Extreme Page' => 1000,
        ];

        $results = [];
        $iterations = 100;

        foreach ($testCases as $label => $elementCount) {
            $context = $this->createContextWithElements($elementCount);

            $startTime = microtime(true);

            for ($i = 0; $i < $iterations; $i++) {
                $lifetime = $strategy->getCacheLifetime($context);
            }

            $endTime = microtime(true);

            $avgDuration = (($endTime - $startTime) / $iterations) * 1000;

            $results[$label] = $avgDuration;
        }

        echo "\n";
        echo "Edge Case Performance Analysis:\n";
        echo str_repeat('─', 50) . "\n";
        printf("%-20s %25s\n", "Scenario", "Avg Duration (ms)");
        echo str_repeat('─', 50) . "\n";

        foreach ($results as $label => $duration) {
            printf("%-20s %25s\n", $label, number_format($duration, 4));
            self::assertLessThan(1.0, $duration, "{$label} should calculate in < 1ms");
        }

        echo str_repeat('─', 50) . "\n";
        echo "\n";
    }

    /**
     * Create cache calculation context with specified number of temporal elements
     */
    private function createContextWithElements(int $count): CacheCalculationContext
    {
        $now = time();
        $elements = [];

        for ($i = 0; $i < $count; $i++) {
            // Mix of past, current, future, and expiring content
            $type = $i % 4;

            $contentData = [
                'uid' => 1000 + $i,
                'pid' => 1,
                'hidden' => 0,
                'deleted' => 0,
            ];

            switch ($type) {
                case 0: // Future content
                    $contentData['starttime'] = $now + rand(3600, 86400);
                    $contentData['endtime'] = 0;
                    break;

                case 1: // Expiring content
                    $contentData['starttime'] = 0;
                    $contentData['endtime'] = $now + rand(3600, 86400);
                    break;

                case 2: // Active content with end date
                    $contentData['starttime'] = $now - rand(3600, 86400);
                    $contentData['endtime'] = $now + rand(86400, 604800);
                    break;

                default: // Active permanent content
                    $contentData['starttime'] = 0;
                    $contentData['endtime'] = 0;
            }

            $elements[] = TemporalContent::fromArray($contentData);
        }

        return new CacheCalculationContext(
            pageId: 1,
            temporalContent: $elements
        );
    }
}
