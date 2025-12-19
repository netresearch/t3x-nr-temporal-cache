<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Functional\Service\Scoping;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepository;
use Netresearch\TemporalCache\Service\RefindexService;
use Netresearch\TemporalCache\Service\Scoping\PerContentScopingStrategy;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional integration tests for PerContentScopingStrategy with real database
 *
 * @covers \Netresearch\TemporalCache\Service\Scoping\PerContentScopingStrategy
 */
final class PerContentScopingIntegrationTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler'];

    protected array $testExtensionsToLoad = [
        'nr_temporal_cache',
    ];

    /**     */
    public function testGetCacheTagsToFlushWorksWithRealDatabase(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/tt_content.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/sys_refindex.csv');

        $refindexService = $this->get(RefindexService::class);
        $repository = $this->get(TemporalContentRepository::class);
        $configuration = $this->get(ExtensionConfiguration::class);
        $context = $this->get(Context::class);

        $strategy = new PerContentScopingStrategy(
            $refindexService,
            $repository,
            $configuration
        );

        $content = new TemporalContent(
            uid: 1,
            tableName: 'tt_content',
            title: 'Test Content',
            pid: 1,
            starttime: \time() + 3600,
            endtime: null,
            languageUid: 0,
            workspaceUid: 0
        );

        $tags = $strategy->getCacheTagsToFlush($content, $context);

        self::assertNotEmpty($tags);
        self::assertContains('pageId_1', $tags);
    }
}
