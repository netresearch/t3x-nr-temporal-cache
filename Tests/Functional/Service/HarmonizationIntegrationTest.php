<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Functional\Service;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Service\HarmonizationService;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional integration tests for HarmonizationService
 *
 * @covers \Netresearch\TemporalCache\Service\HarmonizationService
 */
final class HarmonizationIntegrationTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler'];

    protected array $testExtensionsToLoad = [
        'nr_temporal_cache',
    ];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'nr_temporal_cache' => [
                'harmonization' => [
                    'enabled' => true,
                    'slots' => '00:00,06:00,12:00,18:00',
                    'tolerance' => 3600,
                ],
            ],
        ],
    ];

    /**     */
    public function testHarmonizationWorksWithRealConfiguration(): void
    {
        $configuration = $this->get(ExtensionConfiguration::class);
        $service = new HarmonizationService($configuration);

        // 2021-01-01 00:30:00 should round to 00:00:00
        $input = 1609461000;
        $expected = 1609459200;

        $result = $service->harmonizeTimestamp($input);

        self::assertSame($expected, $result);
    }

    /**     */
    public function testCalculateHarmonizationImpactWorksEndToEnd(): void
    {
        $configuration = $this->get(ExtensionConfiguration::class);
        $service = new HarmonizationService($configuration);

        $midnight = 1609459200;
        $timestamps = [
            $midnight + 600,   // 00:10
            $midnight + 1200,  // 00:20
            $midnight + 1800,  // 00:30
        ];

        $impact = $service->calculateHarmonizationImpact($timestamps);

        self::assertSame(3, $impact['original']);
        self::assertSame(1, $impact['harmonized']);
        self::assertGreaterThan(60.0, $impact['reduction']);
    }
}
