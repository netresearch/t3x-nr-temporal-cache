<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Functional\Service\Scoping;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepository;
use Netresearch\TemporalCache\Service\RefindexService;
use Netresearch\TemporalCache\Service\Scoping\PerContentScopingStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Per-content scoping exists to flush the cache of every page a content element appears on,
 * not just the page it lives on. That difference is the entire feature, and it is only
 * observable when a reference points at a page OTHER than the element's own pid — an
 * assertion on the parent page alone passes just as well when the refindex lookup returns
 * nothing and the strategy falls back to `[$content->pid]`.
 *
 * The reference row here is the advertised case: page 20 embeds content element 1, which
 * lives on page 1.
 */
#[CoversClass(PerContentScopingStrategy::class)]
#[CoversClass(RefindexService::class)]
final class PerContentRefindexResolutionTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler', 'reports'];

    protected array $testExtensionsToLoad = [
        'nr_temporal_cache',
    ];

    public function testAPageReferencingTheElementIsFlushedAlongsideItsParent(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/tt_content.csv');

        // A page that is not the element's pid, referencing content element 1.
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('sys_refindex')
            ->insert('sys_refindex', [
                'hash' => 'refresolution01',
                'tablename' => 'pages',
                'recuid' => 20,
                'field' => 'content',
                'flexpointer' => '',
                'softref_key' => '',
                'ref_table' => 'tt_content',
                'ref_uid' => 1,
                'ref_string' => '',
                'workspace' => 0,
            ]);

        $strategy = new PerContentScopingStrategy(
            $this->get(RefindexService::class),
            $this->get(TemporalContentRepository::class),
            $this->get(ExtensionConfiguration::class)
        );

        $tags = $strategy->getCacheTagsToFlush(
            new TemporalContent(
                uid: 1,
                tableName: 'tt_content',
                title: 'Referenced content',
                pid: 1,
                starttime: \time() + 3600,
                endtime: null,
                languageUid: 0,
                workspaceUid: 0
            ),
            $this->get(Context::class)
        );

        // The parent page, which the fallback would also produce.
        self::assertContains('pageId_1', $tags);

        // The referencing page, which ONLY a working refindex lookup produces. This is the
        // assertion that separates per-content scoping from per-page scoping.
        self::assertContains(
            'pageId_20',
            $tags,
            'The page referencing the element was not flushed, so the sys_refindex lookup '
            . 'returned nothing and the strategy fell back to the parent page — per-content '
            . 'scoping is then indistinguishable from per-page scoping.'
        );
    }
}
