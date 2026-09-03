<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Functional\Service;

use Netresearch\TemporalCache\Service\TemporalMonitorRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * TemporalMonitorRegistry is the documented extension point for monitoring additional
 * tables, and the manual used to describe three ways of using it that all silently did
 * nothing: a Services.yaml entry with `factory: [..., 'registerTable']` (registerTable()
 * returns void, so it cannot build a service), and two variants that register from a
 * service constructor (an unreferenced definition is removed when the container compiles).
 * An integrator following any of them got no error and no monitoring.
 *
 * This exercises the recipe the manual now documents — GeneralUtility::makeInstance() as
 * used from a consuming extension's ext_localconf.php — through the real container, so
 * the documented instructions cannot rot into a no-op again unnoticed.
 */
#[CoversClass(TemporalMonitorRegistry::class)]
final class TemporalMonitorRegistryRecipeTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler', 'reports'];

    protected array $testExtensionsToLoad = [
        'nr_temporal_cache',
    ];

    private const TABLE = 'tx_recipetest_domain_model_event';

    public function testTheDocumentedRegistrationReachesTheRegistryTheExtensionReads(): void
    {
        $fields = ['uid', 'pid', 'title', 'starttime', 'endtime', 'hidden', 'deleted', 'sys_language_uid'];

        // Exactly what the manual tells a consuming extension to put in ext_localconf.php.
        GeneralUtility::makeInstance(TemporalMonitorRegistry::class)
            ->registerTable(self::TABLE, $fields);

        // Read back through the container, not through the same instance: the extension's
        // own services resolve the registry that way, and a registration that only exists
        // on a separate object would be useless.
        $fromContainer = $this->get(TemporalMonitorRegistry::class);

        self::assertTrue(
            $fromContainer->isRegistered(self::TABLE),
            'The table registered the documented way is not visible to the container-'
            . 'resolved registry, so the extension would never query it.'
        );
        self::assertArrayHasKey(self::TABLE, $fromContainer->getAllTables());
        self::assertSame($fields, $fromContainer->getTableFields(self::TABLE));
    }

    public function testPagesAndContentAreMonitoredWithoutAnyRegistration(): void
    {
        $tables = $this->get(TemporalMonitorRegistry::class)->getAllTables();

        self::assertArrayHasKey('pages', $tables);
        self::assertArrayHasKey('tt_content', $tables);
    }
}
