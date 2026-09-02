<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Functional\Controller\Backend;

use Netresearch\TemporalCache\Controller\Backend\TemporalCacheController;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for TemporalCacheController business logic.
 *
 * These tests validate the controller's data preparation and business logic
 * WITHOUT rendering Fluid templates (which requires complex Extbase setup).
 *
 * Tests focus on:
 * - Data filtering logic (filterContent method)
 * - Configuration presets (getConfigurationPresets method)
 * - Recommendations logic (analyzeConfiguration method)
 * - Filter options (getFilterOptions method)
 * - Business logic correctness, not presentation
 *
 * UI/rendering validation should be done in E2E/Acceptance tests.
 */
#[CoversClass(TemporalCacheController::class)]
final class TemporalCacheControllerBusinessLogicTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler', 'reports'];

    protected array $testExtensionsToLoad = [
        'nr_temporal_cache',
    ];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'nr_temporal_cache' => [
                'scoping' => [
                    'strategy' => 'global',
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

    private TemporalCacheController $controller;

    private TemporalContentRepository $repository;




    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/tt_content.csv');

        // Set up TYPO3_REQUEST for TYPO3 12/13 compatibility (ConfigurationManager and PageRenderer require request with applicationType)
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://example.com/', 'GET'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $this->repository = $this->get(TemporalContentRepository::class);

        // Get controller from DI container to ensure all dependencies are injected
        $this->controller = $this->get(TemporalCacheController::class);
    }

    /**
     * Test filterContent method with 'all' filter
     */
    #[Test]
    public function filterContentWithAllFilterReturnsAllContent(): void
    {
        $allContent = $this->repository->findAllWithTemporalFields();

        $filtered = $this->invokePrivateMethod('filterContent', [$allContent, 'all', \time()]);

        self::assertCount(\count($allContent), $filtered, 'All filter should return all content');
    }

    /**
     * Test filterContent method with 'pages' filter
     */
    #[Test]
    public function filterContentWithPagesFilterReturnsOnlyPages(): void
    {
        $allContent = $this->repository->findAllWithTemporalFields();

        $filtered = $this->invokePrivateMethod('filterContent', [$allContent, 'pages', \time()]);

        foreach ($filtered as $item) {
            self::assertTrue($item->isPage(), 'Filtered content should only include pages');
        }
    }

    /**
     * Test filterContent method with 'content' filter
     */
    #[Test]
    public function filterContentWithContentFilterReturnsOnlyContentElements(): void
    {
        $allContent = $this->repository->findAllWithTemporalFields();

        $filtered = $this->invokePrivateMethod('filterContent', [$allContent, 'content', \time()]);

        foreach ($filtered as $item) {
            self::assertTrue($item->isContent(), 'Filtered content should only include content elements');
        }
    }

    /**
     * Test filterContent method with 'active' filter
     */
    #[Test]
    public function filterContentWithActiveFilterReturnsVisibleContent(): void
    {
        $now = \time();
        $allContent = $this->repository->findAllWithTemporalFields();

        $filtered = $this->invokePrivateMethod('filterContent', [$allContent, 'active', $now]);

        foreach ($filtered as $item) {
            self::assertTrue($item->isVisible($now), 'Active filter should only return currently visible content');
        }
    }

    /**
     * Test filterContent method with 'scheduled' filter
     */
    #[Test]
    public function filterContentWithScheduledFilterReturnsFutureContent(): void
    {
        $now = \time();
        $allContent = $this->repository->findAllWithTemporalFields();

        $filtered = $this->invokePrivateMethod('filterContent', [$allContent, 'scheduled', $now]);

        foreach ($filtered as $item) {
            self::assertNotNull($item->starttime, 'Scheduled content should have starttime');
            self::assertGreaterThan($now, $item->starttime, 'Scheduled content starttime should be in future');
        }
    }

    /**
     * Test filterContent method with 'expired' filter
     */
    #[Test]
    public function filterContentWithExpiredFilterReturnsExpiredContent(): void
    {
        $now = \time();
        $allContent = $this->repository->findAllWithTemporalFields();

        $filtered = $this->invokePrivateMethod('filterContent', [$allContent, 'expired', $now]);

        // Ensure we got an array result (even if empty is valid)
        self::assertIsArray($filtered, 'filterContent should return an array');

        foreach ($filtered as $item) {
            self::assertNotNull($item->endtime, 'Expired content should have endtime');
            self::assertLessThan($now, $item->endtime, 'Expired content endtime should be in past');
        }
    }

    /**
     * Test getFilterOptions method returns all expected filters
     */
    #[Test]
    public function getFilterOptionsReturnsAllFilters(): void
    {
        $options = $this->invokePrivateMethod('getFilterOptions', []);

        $expectedFilters = ['all', 'pages', 'content', 'active', 'scheduled', 'expired', 'harmonizable'];

        foreach ($expectedFilters as $filter) {
            self::assertArrayHasKey($filter, $options, "Filter options should include '$filter'");
        }
    }

    /**
     * Test getConfigurationPresets returns expected presets
     */
    #[Test]
    public function getConfigurationPresetsReturnsThreePresets(): void
    {
        $presets = $this->invokePrivateMethod('getConfigurationPresets', []);

        self::assertIsArray($presets);
        self::assertArrayHasKey('simple', $presets, 'Should have simple preset');
        self::assertArrayHasKey('balanced', $presets, 'Should have balanced preset');
        self::assertArrayHasKey('aggressive', $presets, 'Should have aggressive preset');
    }

    /**
     * Test simple preset configuration
     */
    #[Test]
    public function simplePresetHasExpectedConfiguration(): void
    {
        $presets = $this->invokePrivateMethod('getConfigurationPresets', []);

        $simple = $presets['simple'];
        self::assertArrayHasKey('config', $simple);
        self::assertEquals('global', $simple['config']['scoping']['strategy'], 'Simple preset should use global scoping');
        self::assertEquals('dynamic', $simple['config']['timing']['strategy'], 'Simple preset should use dynamic timing');
        self::assertFalse($simple['config']['harmonization']['enabled'], 'Simple preset should disable harmonization');
    }

    /**
     * Test balanced preset configuration
     */
    #[Test]
    public function balancedPresetHasExpectedConfiguration(): void
    {
        $presets = $this->invokePrivateMethod('getConfigurationPresets', []);

        $balanced = $presets['balanced'];
        self::assertArrayHasKey('config', $balanced);
        self::assertEquals('per-page', $balanced['config']['scoping']['strategy'], 'Balanced preset should use per-page scoping');
        self::assertEquals('hybrid', $balanced['config']['timing']['strategy'], 'Balanced preset should use hybrid timing');
        self::assertTrue($balanced['config']['harmonization']['enabled'], 'Balanced preset should enable harmonization');
    }

    /**
     * Test aggressive preset configuration
     */
    #[Test]
    public function aggressivePresetHasExpectedConfiguration(): void
    {
        $presets = $this->invokePrivateMethod('getConfigurationPresets', []);

        $aggressive = $presets['aggressive'];
        self::assertArrayHasKey('config', $aggressive);
        self::assertEquals('per-content', $aggressive['config']['scoping']['strategy'], 'Aggressive preset should use per-content scoping');
        self::assertEquals('scheduler', $aggressive['config']['timing']['strategy'], 'Aggressive preset should use scheduler timing');
        self::assertTrue($aggressive['config']['harmonization']['enabled'], 'Aggressive preset should enable harmonization');
    }

    /**
     * Test analyzeConfiguration returns array of recommendations
     */
    #[Test]
    public function analyzeConfigurationReturnsRecommendationsArray(): void
    {
        $recommendations = $this->invokePrivateMethod('analyzeConfiguration', []);

        // Should return an array (may be empty if configuration is optimal)
        self::assertIsArray($recommendations, 'analyzeConfiguration should return an array');

        // If recommendations exist, they should have expected structure
        foreach ($recommendations as $recommendation) {
            self::assertArrayHasKey('type', $recommendation, 'Recommendation should have type');
            self::assertArrayHasKey('title', $recommendation, 'Recommendation should have title');
            self::assertArrayHasKey('message', $recommendation, 'Recommendation should have message');
        }
    }

    /**
     * Helper method to invoke private/protected methods using reflection
     *
     * @param array<int, mixed> $args
     */
    private function invokePrivateMethod(string $methodName, array $args = []): mixed
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod($methodName);

        return $method->invoke($this->controller, ...$args);
    }
}
