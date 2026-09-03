<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Domain\Model;

use InvalidArgumentException;

/**
 * Value object representing a temporal transition event.
 *
 * Used by scheduler strategy to process transitions that have occurred.
 * Includes workspace and language context for proper cache invalidation.
 *
 * @api This class is part of the public API — see Documentation/Api/Index.rst.
 */
final class TransitionEvent
{
    public function __construct(
        public readonly TemporalContent $content,
        public readonly int $timestamp,
        public readonly string $transitionType,
        public readonly int $workspaceId = 0,
        public readonly int $languageId = 0
    ) {
        if (!\in_array($this->transitionType, ['start', 'end', 'unknown'], true)) {
            throw new InvalidArgumentException('TransitionType must be "start", "end", or "unknown"');
        }
    }

    public function isStartTransition(): bool
    {
        return $this->transitionType === 'start';
    }

    public function isEndTransition(): bool
    {
        return $this->transitionType === 'end';
    }

    public function getLogMessage(): string
    {
        return \sprintf(
            'Transition: %s #%d (%s) - %s at %s (workspace=%d, language=%d)',
            $this->content->tableName,
            $this->content->uid,
            $this->content->title,
            $this->transitionType,
            \date('Y-m-d H:i:s', $this->timestamp),
            $this->workspaceId,
            $this->languageId
        );
    }

}
