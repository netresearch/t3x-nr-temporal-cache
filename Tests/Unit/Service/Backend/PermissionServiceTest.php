<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Service\Backend;

use Netresearch\TemporalCache\Service\Backend\PermissionService;
use Netresearch\TemporalCache\Service\TemporalMonitorRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Netresearch\TemporalCache\Service\Backend\PermissionService
 */
final class PermissionServiceTest extends UnitTestCase
{
    private TemporalMonitorRegistry $monitorRegistry;
    private BackendUserAuthentication&MockObject $backendUser;
    private PermissionService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        // Use real registry - it's a simple singleton data holder
        $this->monitorRegistry = new TemporalMonitorRegistry();
        $this->backendUser = $this->createMock(BackendUserAuthentication::class);

        // Mock global backend user
        $GLOBALS['BE_USER'] = $this->backendUser;

        $this->subject = new PermissionService($this->monitorRegistry);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['BE_USER']);
        parent::tearDown();
    }

    /**     */
    public function testCanModifyTemporalContentReturnsTrueForAdminUser(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(true);

        $result = $this->subject->canModifyTemporalContent();

        self::assertTrue($result);
    }

    /**     */
    public function testCanModifyTemporalContentChecksSpecificTableWhenProvided(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(false);

        $this->backendUser
            ->expects(self::once())
            ->method('check')
            ->with('tables_modify', 'pages')
            ->willReturn(true);

        $result = $this->subject->canModifyTemporalContent('pages');

        self::assertTrue($result);
    }

    /**     */
    public function testCanModifyTemporalContentChecksAllTablesWhenNoTableProvided(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(false);

        // Real registry returns default tables (pages and tt_content)
        $this->backendUser
            ->method('check')
            ->willReturnMap([
                ['tables_modify', 'pages', true],
                ['tables_modify', 'tt_content', true],
            ]);

        $result = $this->subject->canModifyTemporalContent();

        self::assertTrue($result);
    }

    /**     */
    public function testCanModifyTemporalContentReturnsFalseIfAnyTableNotModifiable(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(false);

        // Real registry returns default tables (pages and tt_content)
        $this->backendUser
            ->method('check')
            ->willReturnMap([
                ['tables_modify', 'pages', true],
                ['tables_modify', 'tt_content', false], // No permission for tt_content
            ]);

        $result = $this->subject->canModifyTemporalContent();

        self::assertFalse($result);
    }

    /**     */
    public function testCanAccessModuleReturnsTrueForAdminUser(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(true);

        $result = $this->subject->canAccessModule();

        self::assertTrue($result);
    }

    /**     */
    public function testCanAccessModuleReturnsTrueWhenModuleNotHidden(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(false);

        $this->backendUser
            ->method('getTSConfig')
            ->willReturn([
                'options.' => [
                    'hideModules' => '',
                ],
            ]);

        $result = $this->subject->canAccessModule();

        self::assertTrue($result);
    }

    /**     */
    public function testCanAccessModuleReturnsFalseWhenModuleHidden(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(false);

        $this->backendUser
            ->method('getTSConfig')
            ->willReturn([
                'options.' => [
                    'hideModules' => 'tools_TemporalCache',
                ],
            ]);

        $result = $this->subject->canAccessModule();

        self::assertFalse($result);
    }

    /**     */
    public function testCanAccessModuleReturnsFalseWhenModuleInHiddenList(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(false);

        $this->backendUser
            ->method('getTSConfig')
            ->willReturn([
                'options.' => [
                    'hideModules' => 'web_info, tools_TemporalCache, file_list',
                ],
            ]);

        $result = $this->subject->canAccessModule();

        self::assertFalse($result);
    }

    /**     */
    public function testGetUnmodifiableTablesReturnsEmptyForAdminUser(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(true);

        $result = $this->subject->getUnmodifiableTables();

        self::assertEmpty($result);
    }

    /**     */
    public function testGetUnmodifiableTablesReturnsTablesWithoutPermission(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(false);

        // Real registry returns default tables (pages and tt_content)
        $this->backendUser
            ->method('check')
            ->willReturnMap([
                ['tables_modify', 'pages', true],
                ['tables_modify', 'tt_content', false],
            ]);

        $result = $this->subject->getUnmodifiableTables();

        self::assertCount(1, $result);
        self::assertContains('tt_content', $result);
    }

    /**     */
    public function testIsReadOnlyReturnsTrueWhenCannotModify(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(false);

        // Real registry returns default tables (pages and tt_content)
        $this->backendUser
            ->method('check')
            ->willReturn(false); // No permission for any table

        $result = $this->subject->isReadOnly();

        self::assertTrue($result);
    }

    /**     */
    public function testIsReadOnlyReturnsFalseWhenCanModify(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(true);

        $result = $this->subject->isReadOnly();

        self::assertFalse($result);
    }

    /**     */
    public function testGetPermissionStatusReturnsCompleteStatusForAdminUser(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(true);

        $this->backendUser
            ->method('getTSConfig')
            ->willReturn([
                'options.' => [
                    'hideModules' => '',
                ],
            ]);

        $result = $this->subject->getPermissionStatus();

        self::assertTrue($result['isAdmin']);
        self::assertTrue($result['canModify']);
        self::assertTrue($result['canAccessModule']);
        self::assertEmpty($result['unmodifiableTables']);
    }

    /**     */
    public function testGetPermissionStatusReturnsCompleteStatusForNonAdminUser(): void
    {
        $this->backendUser
            ->method('isAdmin')
            ->willReturn(false);

        // Real registry returns default tables (pages and tt_content)
        $this->backendUser
            ->method('check')
            ->willReturnMap([
                ['tables_modify', 'pages', true],
                ['tables_modify', 'tt_content', false],
            ]);

        $this->backendUser
            ->method('getTSConfig')
            ->willReturn([
                'options.' => [
                    'hideModules' => '',
                ],
            ]);

        $result = $this->subject->getPermissionStatus();

        self::assertFalse($result['isAdmin']);
        self::assertFalse($result['canModify']); // Cannot modify all tables
        self::assertTrue($result['canAccessModule']);
        self::assertCount(1, $result['unmodifiableTables']);
        self::assertContains('tt_content', $result['unmodifiableTables']);
    }
}
