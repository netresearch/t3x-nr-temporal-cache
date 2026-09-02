<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Service;

use RuntimeException;

/**
 * Shared selection logic for the scoping and timing strategy factories.
 *
 * Both factories receive every service carrying their family's tag through
 * !tagged_iterator and activate the one whose getName() matches the extension
 * configuration, falling back to the first tagged service — which is the one with
 * the highest tag priority. Holding that in one place is what stops the two
 * copies drifting apart; they were identical apart from their type names.
 *
 * The argument is iterable, not array: a tagged_iterator is a generator, so it
 * cannot be indexed and the fallback has to be captured while iterating.
 */
trait SelectsNamedStrategy
{
    /**
     * @template TStrategy of NamedStrategyInterface
     *
     * @param iterable<array-key, TStrategy> $strategies    every service tagged for this family
     * @param string                         $configuredName getName() to activate
     * @param string                         $emptyMessage   thrown when nothing is tagged at all
     *
     * @return TStrategy
     */
    private function selectNamedStrategy(
        iterable $strategies,
        string $configuredName,
        string $emptyMessage
    ): NamedStrategyInterface {
        $firstStrategy = null;

        foreach ($strategies as $strategy) {
            $firstStrategy ??= $strategy;

            if ($strategy->getName() === $configuredName) {
                return $strategy;
            }
        }

        return $firstStrategy ?? throw new RuntimeException($emptyMessage);
    }
}
