<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Privatization\Rector\Property\PrivatizeFinalClassPropertyRector;

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
        // Scheduler tasks are persisted as a whole serialized object in
        // tx_scheduler_task.serialized_task_object. PHP encodes property
        // visibility into the serialized key name, so turning a `protected`
        // property `private` orphans the values of every task row that already
        // exists — the task comes back with an uninitialized property.
        PrivatizeFinalClassPropertyRector::class => [
            __DIR__ . '/../Classes/Task',
        ],
    ]);
};
