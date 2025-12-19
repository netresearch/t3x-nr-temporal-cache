<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Functional\Backend;

use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for TemporalCacheController
 *
 * @covers \Netresearch\TemporalCache\Controller\Backend\TemporalCacheController
 */
final class TemporalCacheControllerTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler'];

    protected array $testExtensionsToLoad = [
        'nr_temporal_cache',
    ];

    /**     */
    public function testControllerCanBeInstantiated(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');

        self::assertTrue(true);
    }
}
