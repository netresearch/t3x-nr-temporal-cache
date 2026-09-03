<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Domain\Model;

/**
 * Value object representing temporal content (pages or content elements with starttime/endtime).
 *
 * Immutable domain model following DDD principles.
 *
 * @api This class is part of the public API — see Documentation/Api/Index.rst.
 */
final class TemporalContent
{
    public function __construct(
        public readonly int $uid,
        public readonly string $tableName,
        public readonly string $title,
        public readonly int $pid,
        public readonly ?int $starttime,
        public readonly ?int $endtime,
        public readonly int $languageUid,
        public readonly int $workspaceUid,
        public readonly bool $hidden = false,
        public readonly bool $deleted = false
    ) {
    }

    public function hasTemporalFields(): bool
    {
        return $this->starttime !== null || $this->endtime !== null;
    }

    public function getNextTransition(int $currentTime): ?int
    {
        $transitions = \array_filter(
            [$this->starttime, $this->endtime],
            fn (?int $t): bool => $t !== null && $t > $currentTime
        );

        return $transitions === [] ? null : \min($transitions);
    }

    public function getContentType(): string
    {
        return $this->tableName === 'pages' ? 'page' : 'content';
    }

    public function isPage(): bool
    {
        return $this->tableName === 'pages';
    }

    public function isContent(): bool
    {
        return $this->tableName === 'tt_content';
    }

    public function isVisible(int $currentTime): bool
    {
        if ($this->hidden || $this->deleted) {
            return false;
        }

        if ($this->starttime !== null && $this->starttime > $currentTime) {
            return false;
        }

        if ($this->endtime !== null && $this->endtime < $currentTime) {
            return false;
        }

        return true;
    }

    public function getTransitionType(int $timestamp): ?string
    {
        if ($this->starttime === $timestamp) {
            return 'start';
        }

        if ($this->endtime === $timestamp) {
            return 'end';
        }

        return null;
    }
}
