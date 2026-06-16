<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Functional\Service;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Service\HarmonizationService;
use TYPO3\CMS\Core\Database\ConnectionPool;
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
        $service = new HarmonizationService($configuration, $this->get(ConnectionPool::class));

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
        $service = new HarmonizationService($configuration, $this->get(ConnectionPool::class));

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

    /**     */
    public function testHarmonizeContentPersistsChangesToDatabase(): void
    {
        $connectionPool = $this->get(ConnectionPool::class);
        $connection = $connectionPool->getConnectionForTable('tt_content');

        // 2021-01-01 00:30:00 UTC should round down to 00:00:00 (1609459200)
        $unharmonized = 1609461000;
        $expected = 1609459200;

        $connection->insert('tt_content', [
            'uid' => 4242,
            'pid' => 1,
            'header' => 'Persistence test',
            'starttime' => $unharmonized,
            'endtime' => 0,
            'sys_language_uid' => 0,
        ]);

        $content = new TemporalContent(
            uid: 4242,
            tableName: 'tt_content',
            title: 'Persistence test',
            pid: 1,
            starttime: $unharmonized,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0,
            hidden: false,
            deleted: false,
        );

        $service = new HarmonizationService($this->get(ExtensionConfiguration::class), $connectionPool);
        $result = $service->harmonizeContent($content, false);

        self::assertTrue($result['success']);

        $persisted = $connection
            ->select(['starttime'], 'tt_content', ['uid' => 4242])
            ->fetchAssociative();

        self::assertIsArray($persisted);
        self::assertSame($expected, (int)$persisted['starttime'], 'starttime must be persisted as the harmonized slot value');
    }

    /**     */
    public function testHarmonizeContentPersistsToPagesTableNotTtContent(): void
    {
        $connectionPool = $this->get(ConnectionPool::class);
        $pagesConnection = $connectionPool->getConnectionForTable('pages');
        $contentConnection = $connectionPool->getConnectionForTable('tt_content');

        $unharmonized = 1609461000; // 00:30:00 -> 00:00:00 (1609459200)
        $expected = 1609459200;

        // A page AND a tt_content row sharing the same uid (5) to prove table-aware routing:
        // harmonizing the page must NOT touch the same-uid content element.
        $pagesConnection->insert('pages', [
            'uid' => 5,
            'pid' => 1,
            'title' => 'Temporal page',
            'starttime' => $unharmonized,
            'endtime' => 0,
            'sys_language_uid' => 0,
        ]);
        $contentConnection->insert('tt_content', [
            'uid' => 5,
            'pid' => 1,
            'header' => 'Unrelated same-uid content',
            'starttime' => $unharmonized,
            'endtime' => 0,
            'sys_language_uid' => 0,
        ]);

        $page = new TemporalContent(
            uid: 5,
            tableName: 'pages',
            title: 'Temporal page',
            pid: 1,
            starttime: $unharmonized,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0,
            hidden: false,
            deleted: false,
        );

        $service = new HarmonizationService($this->get(ExtensionConfiguration::class), $connectionPool);
        $result = $service->harmonizeContent($page, false);

        self::assertTrue($result['success']);

        $persistedPage = $pagesConnection->select(['starttime'], 'pages', ['uid' => 5])->fetchAssociative();
        $untouchedContent = $contentConnection->select(['starttime'], 'tt_content', ['uid' => 5])->fetchAssociative();

        self::assertIsArray($persistedPage);
        self::assertIsArray($untouchedContent);
        self::assertSame($expected, (int)$persistedPage['starttime'], 'page starttime must be harmonized');
        self::assertSame($unharmonized, (int)$untouchedContent['starttime'], 'same-uid tt_content must be untouched');
    }

    /**     */
    public function testHarmonizeContentDryRunDoesNotPersist(): void
    {
        $connectionPool = $this->get(ConnectionPool::class);
        $connection = $connectionPool->getConnectionForTable('tt_content');

        $unharmonized = 1609461000;

        $connection->insert('tt_content', [
            'uid' => 4243,
            'pid' => 1,
            'header' => 'Dry-run test',
            'starttime' => $unharmonized,
            'endtime' => 0,
            'sys_language_uid' => 0,
        ]);

        $content = new TemporalContent(
            uid: 4243,
            tableName: 'tt_content',
            title: 'Dry-run test',
            pid: 1,
            starttime: $unharmonized,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0,
            hidden: false,
            deleted: false,
        );

        $service = new HarmonizationService($this->get(ExtensionConfiguration::class), $connectionPool);
        $result = $service->harmonizeContent($content, true);

        self::assertTrue($result['success']);

        $persisted = $connection
            ->select(['starttime'], 'tt_content', ['uid' => 4243])
            ->fetchAssociative();

        self::assertIsArray($persisted);
        self::assertSame($unharmonized, (int)$persisted['starttime'], 'dry-run must not modify the database');
    }
}
