<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Functional\Integration;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepository;
use Netresearch\TemporalCache\Service\HarmonizationService;
use Netresearch\TemporalCache\Service\Scoping\ScopingStrategyFactory;
use Netresearch\TemporalCache\Service\Timing\TimingStrategyFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Complete workflow integration tests
 *
 * Tests the complete flow from configuration to cache invalidation
 */
final class CompleteWorkflowTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler'];

    protected array $testExtensionsToLoad = [
        'nr_temporal_cache',
    ];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'temporal_cache' => [
                'scoping' => [
                    'strategy' => 'per-content',
                    'use_refindex' => true,
                ],
                'timing' => [
                    'strategy' => 'dynamic',
                ],
                'harmonization' => [
                    'enabled' => true,
                    'slots' => '00:00,06:00,12:00,18:00',
                    'tolerance' => 3600,
                ],
            ],
        ],
    ];

    /**     */
    public function testCompleteWorkflowWorksEndToEnd(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tt_content.csv');

        $configuration = $this->get(ExtensionConfiguration::class);
        $scopingFactory = $this->get(ScopingStrategyFactory::class);
        $timingFactory = $this->get(TimingStrategyFactory::class);
        $repository = $this->get(TemporalContentRepository::class);
        $harmonization = $this->get(HarmonizationService::class);

        // Verify configuration loaded correctly
        self::assertSame('per-content', $configuration->getScopingStrategy());
        self::assertSame('dynamic', $configuration->getTimingStrategy());
        self::assertTrue($configuration->isHarmonizationEnabled());

        // Verify strategies can be instantiated
        $scopingStrategy = $scopingFactory->get();
        $timingStrategy = $timingFactory->get();

        self::assertSame('per-content', $scopingStrategy->getName());
        self::assertSame('dynamic', $timingStrategy->getName());

        // Verify repository can find temporal content
        $allContent = $repository->findAllWithTemporalFields(0, 0);
        self::assertIsArray($allContent);

        // Verify harmonization works
        $harmonized = $harmonization->harmonizeTimestamp(\time());
        self::assertIsInt($harmonized);
    }

    /**     */
    public function testBackwardCompatibilityWithDefaultConfiguration(): void
    {
        $configuration = $this->get(ExtensionConfiguration::class);

        // Default should be Phase 1 behavior
        self::assertSame('global', $configuration->getScopingStrategy());
        self::assertSame('dynamic', $configuration->getTimingStrategy());
        self::assertFalse($configuration->isHarmonizationEnabled());
    }
}
