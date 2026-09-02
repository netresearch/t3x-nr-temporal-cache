<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Privatization\Rector\Property\PrivatizeFinalClassPropertyRector;
use Ssch\TYPO3Rector\General\Renaming\ConstantsToBackedEnumRector;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;
use Ssch\TYPO3Rector\TYPO314\v0\MigrateButtonBarMenuAndMenuRegistryMakeMethodsToComponentFactoryRector;
use Ssch\TYPO3Rector\TYPO314\v0\MigrateLabelReferenceToDomainSyntaxRector;

$configure = require_once __DIR__ . '/../.Build/vendor/netresearch/typo3-ci-workflows/config/rector/rector.php';

return static function (RectorConfig $rectorConfig) use ($configure): void {
    // Shared org base config: code-quality sets, rule skips, importNames,
    // the package's ergebnis-free phpstan-rector.neon, and phpVersion.
    $configure($rectorConfig, __DIR__ . '/..');

    // The shared config targets PHP 8.2, but this extension still supports
    // php ^8.1 and CI lints every matrix version. phpVersion() replaces, so
    // pinning the real floor keeps 8.2-only syntax (readonly classes, DNF
    // types) out of the tree even though the shared 8.2 level set stays
    // registered — sets() is additive and cannot be unset.
    $rectorConfig->phpVersion(80100);

    // paths() REPLACES, so the shared default list is restated here plus Tests/.
    $rectorConfig->paths([
        __DIR__ . '/../Classes',
        __DIR__ . '/../Configuration',
        __DIR__ . '/../Resources',
        __DIR__ . '/../Tests',
        __DIR__ . '/../ext_localconf.php',
    ]);

    $rectorConfig->skip([
        // This extension still supports TYPO3 v12: constant-to-enum
        // substitutions (e.g. Icon::SIZE_* -> IconSize::*) would break the
        // deliberate class_exists() compat shims that keep v12 working.
        ConstantsToBackedEnumRector::class,
        // Scheduler tasks are persisted as a whole serialized object in
        // tx_scheduler_task.serialized_task_object. PHP encodes property
        // visibility into the serialized key name, so turning a `protected`
        // property `private` orphans the values of every task row that already
        // exists — the task comes back with an uninitialized property.
        PrivatizeFinalClassPropertyRector::class => [
            __DIR__ . '/../Classes/Task',
        ],
        // Both rules below come from the v14 set added at the bottom of this
        // file and rewrite to APIs that exist only in v14, so applying them
        // would drop the ^12.4 || ^13.0 half of the composer.json constraint.
        // Remove them when v12/v13 support is dropped.
        //
        // Injects TYPO3\CMS\Backend\Template\Components\ComponentFactory into
        // the controller constructor. That class is new in v14; on v12/v13 the
        // service cannot be autowired and the module fails to instantiate. The
        // methods it replaces (ButtonBar::makeLinkButton(),
        // MenuRegistry::makeMenu(), ...) are only deprecated in v14, not
        // removed, so the current code runs on all three majors.
        MigrateButtonBarMenuAndMenuRegistryMakeMethodsToComponentFactoryRector::class,
        // Rewrites 'LLL:EXT:.../locallang_mod.xlf:key' to the semantic label
        // domain syntax 'nr_temporal_cache.mod:key'. LanguageService resolves
        // domains only from v14 on; v12/v13 pass the string through unresolved.
        // It also mis-transforms the concatenated menu label
        // ('...:menu.' . $action becomes '...:menu.'), dropping the variable.
        MigrateLabelReferenceToDomainSyntaxRector::class,
    ]);

    // TYPO3 migration level: v14, the HIGHEST major this extension supports.
    // An UP_TO set is cumulative — up-to-typo3-14.php itself registers
    // UP_TO_TYPO3_13 plus the v14 set — so the level tracks the newest version
    // in composer.json (^12.4 || ^13.0 || ^14.0), not the oldest; raise it when
    // v15 support is added (typo3-ci-workflows#155). Compatibility with the
    // still-supported v12 is kept by the skip() list above, not by holding the
    // level back.
    $rectorConfig->sets([
        Typo3LevelSetList::UP_TO_TYPO3_14,
    ]);
};
