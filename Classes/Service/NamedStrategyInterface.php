<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Service;

/**
 * A strategy that identifies itself by name.
 *
 * Both strategy families are resolved the same way: the factory receives every
 * service carrying the family's tag and picks the one whose name matches the
 * extension configuration. This interface is what lets that selection live in
 * one place (SelectsNamedStrategy) instead of once per family.
 *
 * Purely additive: ScopingStrategyInterface and TimingStrategyInterface both
 * already declared getName(), so every existing implementation satisfies it
 * without change.
 *
 * @api This class is part of the public API — see Documentation/Api/Index.rst.
 */
interface NamedStrategyInterface
{
    /**
     * Identifier used to select this strategy from the extension configuration.
     */
    public function getName(): string;
}
