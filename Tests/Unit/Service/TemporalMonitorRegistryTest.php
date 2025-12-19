<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Service;

use Netresearch\TemporalCache\Service\TemporalMonitorRegistry;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Service\TemporalMonitorRegistry
 */
final class TemporalMonitorRegistryTest extends UnitTestCase
{
    private TemporalMonitorRegistry $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new TemporalMonitorRegistry();
    }

    /**     */
    public function testDefaultTablesAreRegistered(): void
    {
        self::assertTrue($this->subject->isRegistered('pages'));
        self::assertTrue($this->subject->isRegistered('tt_content'));
    }

    /**     */
    public function testGetAllTablesIncludesDefaultTables(): void
    {
        $tables = $this->subject->getAllTables();

        self::assertArrayHasKey('pages', $tables);
        self::assertArrayHasKey('tt_content', $tables);
        self::assertSame(2, \count($tables)); // Only defaults initially
    }

    /**     */
    public function testGetTotalTableCountIncludesDefaults(): void
    {
        self::assertSame(2, $this->subject->getTotalTableCount());
    }

    /**     */
    public function testGetCustomTableCountIsZeroInitially(): void
    {
        self::assertSame(0, $this->subject->getCustomTableCount());
    }

    /**     */
    public function testGetCustomTablesIsEmptyInitially(): void
    {
        self::assertEmpty($this->subject->getCustomTables());
    }

    /**     */
    public function testRegisterTableAddsCustomTable(): void
    {
        $this->subject->registerTable('tx_news_domain_model_news');

        self::assertTrue($this->subject->isRegistered('tx_news_domain_model_news'));
        self::assertSame(1, $this->subject->getCustomTableCount());
        self::assertSame(3, $this->subject->getTotalTableCount());
    }

    /**     */
    public function testRegisterTableWithDefaultFieldsUsesDefaults(): void
    {
        $this->subject->registerTable('tx_news_domain_model_news');

        $fields = $this->subject->getTableFields('tx_news_domain_model_news');

        self::assertIsArray($fields);
        self::assertContains('uid', $fields);
        self::assertContains('starttime', $fields);
        self::assertContains('endtime', $fields);
    }

    /**     */
    public function testRegisterTableWithCustomFieldsUsesCustom(): void
    {
        $customFields = ['uid', 'starttime', 'endtime', 'custom_field'];

        $this->subject->registerTable('tx_news_domain_model_news', $customFields);

        $fields = $this->subject->getTableFields('tx_news_domain_model_news');

        self::assertSame($customFields, $fields);
    }

    /**     */
    public function testRegisterTableThrowsExceptionForEmptyTableName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1730289600);

        $this->subject->registerTable('');
    }

    /**     */
    public function testRegisterTableThrowsExceptionForDefaultTable(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1730289601);

        $this->subject->registerTable('pages');
    }

    /**     */
    public function testRegisterTableThrowsExceptionWhenMissingUid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1730289602);

        $this->subject->registerTable('tx_news_domain_model_news', ['starttime', 'endtime']);
    }

    /**     */
    public function testRegisterTableThrowsExceptionWhenMissingStarttime(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1730289602);

        $this->subject->registerTable('tx_news_domain_model_news', ['uid', 'endtime']);
    }

    /**     */
    public function testRegisterTableThrowsExceptionWhenMissingEndtime(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1730289602);

        $this->subject->registerTable('tx_news_domain_model_news', ['uid', 'starttime']);
    }

    /**     */
    public function testUnregisterTableRemovesCustomTable(): void
    {
        $this->subject->registerTable('tx_news_domain_model_news');

        self::assertTrue($this->subject->isRegistered('tx_news_domain_model_news'));

        $this->subject->unregisterTable('tx_news_domain_model_news');

        self::assertFalse($this->subject->isRegistered('tx_news_domain_model_news'));
        self::assertSame(0, $this->subject->getCustomTableCount());
    }

    /**     */
    public function testUnregisterTableDoesNotAffectDefaultTables(): void
    {
        // Attempting to unregister default table should not cause error
        $this->subject->unregisterTable('pages');

        // Default table still registered
        self::assertTrue($this->subject->isRegistered('pages'));
    }

    /**     */
    public function testUnregisterNonExistentTableDoesNotCauseError(): void
    {
        $this->subject->unregisterTable('non_existent_table');

        self::assertFalse($this->subject->isRegistered('non_existent_table'));
    }

    /**     */
    public function testIsRegisteredReturnsFalseForUnregisteredTable(): void
    {
        self::assertFalse($this->subject->isRegistered('tx_news_domain_model_news'));
    }

    /**     */
    public function testGetAllTablesIncludesCustomTables(): void
    {
        $this->subject->registerTable('tx_news_domain_model_news');
        $this->subject->registerTable('tx_events_domain_model_event');

        $tables = $this->subject->getAllTables();

        self::assertCount(4, $tables); // 2 defaults + 2 custom
        self::assertArrayHasKey('pages', $tables);
        self::assertArrayHasKey('tt_content', $tables);
        self::assertArrayHasKey('tx_news_domain_model_news', $tables);
        self::assertArrayHasKey('tx_events_domain_model_event', $tables);
    }

    /**     */
    public function testGetCustomTablesReturnsOnlyCustomTables(): void
    {
        $this->subject->registerTable('tx_news_domain_model_news');
        $this->subject->registerTable('tx_events_domain_model_event');

        $customTables = $this->subject->getCustomTables();

        self::assertCount(2, $customTables);
        self::assertArrayHasKey('tx_news_domain_model_news', $customTables);
        self::assertArrayHasKey('tx_events_domain_model_event', $customTables);
        self::assertArrayNotHasKey('pages', $customTables);
        self::assertArrayNotHasKey('tt_content', $customTables);
    }

    /**     */
    public function testGetTableFieldsReturnsDefaultFields(): void
    {
        $fields = $this->subject->getTableFields('pages');

        self::assertIsArray($fields);
        self::assertContains('uid', $fields);
        self::assertContains('title', $fields);
        self::assertContains('starttime', $fields);
        self::assertContains('endtime', $fields);
    }

    /**     */
    public function testGetTableFieldsReturnsCustomFields(): void
    {
        $customFields = ['uid', 'starttime', 'endtime', 'custom_field'];

        $this->subject->registerTable('tx_news_domain_model_news', $customFields);

        $fields = $this->subject->getTableFields('tx_news_domain_model_news');

        self::assertSame($customFields, $fields);
    }

    /**     */
    public function testGetTableFieldsReturnsNullForUnregisteredTable(): void
    {
        $fields = $this->subject->getTableFields('non_existent_table');

        self::assertNull($fields);
    }

    /**     */
    public function testClearCustomTablesClearsOnlyCustomTables(): void
    {
        $this->subject->registerTable('tx_news_domain_model_news');
        $this->subject->registerTable('tx_events_domain_model_event');

        self::assertSame(2, $this->subject->getCustomTableCount());

        $this->subject->clearCustomTables();

        self::assertSame(0, $this->subject->getCustomTableCount());
        self::assertTrue($this->subject->isRegistered('pages')); // Defaults still there
        self::assertTrue($this->subject->isRegistered('tt_content'));
        self::assertFalse($this->subject->isRegistered('tx_news_domain_model_news'));
        self::assertFalse($this->subject->isRegistered('tx_events_domain_model_event'));
    }

    /**     */
    public function testMultipleRegistrationsCombineCorrectly(): void
    {
        $this->subject->registerTable('tx_news_domain_model_news');
        $this->subject->registerTable('tx_events_domain_model_event');
        $this->subject->registerTable('tx_blog_domain_model_post');

        self::assertSame(3, $this->subject->getCustomTableCount());
        self::assertSame(5, $this->subject->getTotalTableCount());

        $allTables = $this->subject->getAllTables();
        self::assertCount(5, $allTables);
    }
}
