<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Functional\Service;

use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepository;
use Netresearch\TemporalCache\Service\Scoping\GlobalScopingStrategy;
use Netresearch\TemporalCache\Service\Scoping\PerPageScopingStrategy;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Proves that per-page scoping narrows the dynamic cache lifetime to the page being rendered:
 * content-element transitions are scoped per page, while page transitions stay site-wide.
 *
 * @covers \Netresearch\TemporalCache\Domain\Repository\TemporalContentRepository
 * @covers \Netresearch\TemporalCache\Service\Scoping\PerPageScopingStrategy
 * @covers \Netresearch\TemporalCache\Service\Scoping\GlobalScopingStrategy
 */
final class PageAwareScopingTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler'];

    protected array $testExtensionsToLoad = [
        'nr_temporal_cache',
    ];

    private int $now;

    protected function setUp(): void
    {
        parent::setUp();
        // Base on the real clock: the scoping strategies call time() internally, so the
        // inserted records must be comfortably in the future (offsets >= 500s).
        $this->now = \time();

        $pages = $this->get(ConnectionPool::class)->getConnectionForTable('pages');
        $content = $this->get(ConnectionPool::class)->getConnectionForTable('tt_content');

        // Two pages with their own (page) transitions.
        $pages->insert('pages', ['uid' => 10, 'pid' => 1, 'title' => 'P10', 'starttime' => $this->now + 3000, 'endtime' => 0, 'sys_language_uid' => 0]);
        $pages->insert('pages', ['uid' => 20, 'pid' => 1, 'title' => 'P20', 'starttime' => $this->now + 5000, 'endtime' => 0, 'sys_language_uid' => 0]);

        // Content elements on each page with earlier transitions.
        $content->insert('tt_content', ['uid' => 110, 'pid' => 10, 'header' => 'C-on-10', 'starttime' => $this->now + 1000, 'endtime' => 0, 'sys_language_uid' => 0]);
        $content->insert('tt_content', ['uid' => 210, 'pid' => 20, 'header' => 'C-on-20', 'starttime' => $this->now + 500, 'endtime' => 0, 'sys_language_uid' => 0]);

        // Deleted and hidden content on page 10 with the EARLIEST times: must be ignored.
        $content->insert('tt_content', ['uid' => 111, 'pid' => 10, 'header' => 'deleted', 'starttime' => $this->now + 100, 'endtime' => 0, 'sys_language_uid' => 0, 'deleted' => 1]);
        $content->insert('tt_content', ['uid' => 112, 'pid' => 10, 'header' => 'hidden', 'starttime' => $this->now + 200, 'endtime' => 0, 'sys_language_uid' => 0, 'hidden' => 1]);
    }

    /**     */
    public function testGetNextPageTransitionIgnoresContentElements(): void
    {
        $repository = $this->get(TemporalContentRepository::class);

        // Only page transitions count; the earliest page starttime is page 10 at +3000.
        self::assertSame($this->now + 3000, $repository->getNextPageTransition($this->now, 0, 0));
    }

    /**     */
    public function testGetNextContentTransitionIsScopedToPageAndExcludesDeletedHidden(): void
    {
        $repository = $this->get(TemporalContentRepository::class);

        // Page 10: only the visible content (+1000), NOT the deleted (+100) or hidden (+200).
        self::assertSame($this->now + 1000, $repository->getNextContentTransitionForPage(10, $this->now, 0, 0));

        // Page 20: only its own content (+500).
        self::assertSame($this->now + 500, $repository->getNextContentTransitionForPage(20, $this->now, 0, 0));
    }

    /**     */
    public function testPerPageScopingNarrowsTransitionPerPage(): void
    {
        $repository = $this->get(TemporalContentRepository::class);
        $context = new Context();
        $strategy = new PerPageScopingStrategy($repository);

        // Page 10: min(next page transition +3000, content on 10 +1000) = +1000.
        // Crucially NOT the +500 content that lives on page 20.
        self::assertSame($this->now + 1000, $strategy->getNextTransition($context, 10));

        // Page 20: min(+3000, content on 20 +500) = +500.
        self::assertSame($this->now + 500, $strategy->getNextTransition($context, 20));
    }

    /**     */
    public function testGlobalScopingReturnsEarliestTransitionRegardlessOfPage(): void
    {
        $repository = $this->get(TemporalContentRepository::class);
        $context = new Context();
        $strategy = new GlobalScopingStrategy($repository);

        // Global ignores the page id: earliest visible transition site-wide is +500.
        self::assertSame($this->now + 500, $strategy->getNextTransition($context, 10));
        self::assertSame($this->now + 500, $strategy->getNextTransition($context, 20));
    }
}
