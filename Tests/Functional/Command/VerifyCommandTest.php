<?php

declare(strict_types=1);

/*
 * SPDX-License-Identifier: GPL-2.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 */

namespace Netresearch\TemporalCache\Tests\Functional\Command;

use Netresearch\TemporalCache\Command\VerifyCommand;
use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * temporalcache:verify against a real database schema: the index and field
 * checks read it through the DBAL schema introspection of the installed
 * TYPO3 version (DBAL 3 on v12, DBAL 4 on v13/v14).
 */
#[CoversClass(VerifyCommand::class)]
final class VerifyCommandTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler', 'reports'];

    protected array $testExtensionsToLoad = [
        'nr_temporal_cache',
    ];

    public function testDatabaseChecksPassOnTheInstalledSchema(): void
    {
        $tester = $this->createTester();
        $status = $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        $display = $tester->getDisplay();

        self::assertSame(0, $status, $display);
        self::assertStringNotContainsString('MISSING', $display);
        // The verbose field table lists every checked field with its status.
        self::assertMatchesRegularExpression('/tt_content\s+\|\s+sys_language_uid\s+\|\s+OK/', $display);
    }

    public function testDroppedIndexIsReportedMissing(): void
    {
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('pages');
        $schemaManager = $connection->createSchemaManager();
        $dropped = [];
        foreach ($schemaManager->introspectSchema()->getTable('pages')->getIndexes() as $name => $index) {
            if (\str_contains((string)$name, 'temporalcache_starttime')) {
                $schemaManager->dropIndex($name, 'pages');
                $dropped[] = $index;
            }
        }

        try {
            self::assertCount(1, $dropped, 'the starttime index of ext_tables.sql was not found on pages');

            $tester = $this->createTester();
            $status = $tester->execute([]);

            self::assertSame(1, $status);
            self::assertMatchesRegularExpression('/pages\s+\|\s+starttime\s+\|\s+MISSING/', $tester->getDisplay());
        } finally {
            // The schema outlives this test where the testing framework only
            // truncates the tables between tests (MySQL/MariaDB/PostgreSQL),
            // so the index is put back for the tests that run after it.
            foreach ($dropped as $index) {
                $schemaManager->createIndex($index, 'pages');
            }
        }
    }

    private function createTester(): CommandTester
    {
        return new CommandTester(new VerifyCommand(
            $this->get(ConnectionPool::class),
            $this->get(ExtensionConfiguration::class),
        ));
    }
}
