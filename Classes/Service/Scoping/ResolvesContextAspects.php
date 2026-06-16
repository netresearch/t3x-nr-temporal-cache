<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Service\Scoping;

use TYPO3\CMS\Core\Context\Context;

/**
 * Resolves workspace and language ids from the TYPO3 context.
 *
 * Shared by the scoping strategies so the aspect lookup and its type assertions live
 * in a single place.
 */
trait ResolvesContextAspects
{
    private function resolveWorkspaceId(Context $context): int
    {
        $workspaceId = $context->getPropertyFromAspect('workspace', 'id', 0);
        \assert(\is_int($workspaceId));

        return $workspaceId;
    }

    private function resolveLanguageId(Context $context): int
    {
        $languageId = $context->getPropertyFromAspect('language', 'id', 0);
        \assert(\is_int($languageId));

        return $languageId;
    }
}
