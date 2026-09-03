<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Configuration;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration as Typo3ExtensionConfiguration;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Central configuration management for temporal cache extension.
 *
 * Provides type-safe access to all extension configuration with sensible defaults.
 *
 * @internal Not covered by the public API — see Documentation/Api/Index.rst.
 */
class ExtensionConfiguration implements SingletonInterface
{
    private const EXT_KEY = 'nr_temporal_cache';

    /** @var array<string, mixed> */
    private array $config;

    public function __construct(
        private readonly ?Typo3ExtensionConfiguration $extensionConfiguration = null
    ) {
        $rawConfig = $this->extensionConfiguration?->get(self::EXT_KEY);
        \assert(\is_array($rawConfig) || $rawConfig === null);
        /** @var array<string, mixed> $config */
        $config = $rawConfig ?? [];
        $this->config = $config;
    }

    // Scoping Configuration

    public function getScopingStrategy(): string
    {
        $scoping = $this->config['scoping'] ?? [];
        \assert(\is_array($scoping));
        $strategy = $scoping['strategy'] ?? 'global';
        \assert(\is_string($strategy));
        return $strategy;
    }

    public function useRefindex(): bool
    {
        $scoping = $this->config['scoping'] ?? [];
        \assert(\is_array($scoping));
        return (bool)($scoping['use_refindex'] ?? true);
    }

    // Timing Configuration

    public function getTimingStrategy(): string
    {
        $timing = $this->config['timing'] ?? [];
        \assert(\is_array($timing));
        $strategy = $timing['strategy'] ?? 'dynamic';
        \assert(\is_string($strategy));
        return $strategy;
    }

    /**
     * Keyed by TemporalContent::getContentType(), which HybridTimingStrategy uses to
     * look the rule up. That is why the page key is 'page' and not 'pages'.
     *
     * @return array{page: string, content: string}
     */
    public function getTimingRules(): array
    {
        $timing = $this->config['timing'] ?? [];
        \assert(\is_array($timing));
        $hybrid = $timing['hybrid'] ?? [];
        \assert(\is_array($hybrid));
        $pages = $hybrid['pages'] ?? 'dynamic';
        \assert(\is_string($pages));
        $content = $hybrid['content'] ?? 'scheduler';
        \assert(\is_string($content));

        // Keyed by TemporalContent::getContentType(), which yields 'page' (singular)
        // for the pages table. The extension configuration key stays 'pages'; mapping
        // it here is what makes timing.hybrid.pages reach HybridTimingStrategy at all.
        return [
            'page' => $pages,
            'content' => $content,
        ];
    }

    // Harmonization Configuration

    public function isHarmonizationEnabled(): bool
    {
        $harmonization = $this->config['harmonization'] ?? [];
        \assert(\is_array($harmonization));
        return (bool)($harmonization['enabled'] ?? false);
    }

    /**
     * @return string[]
     */
    public function getHarmonizationSlots(): array
    {
        $harmonization = $this->config['harmonization'] ?? [];
        \assert(\is_array($harmonization));
        $slots = $harmonization['slots'] ?? '00:00,06:00,12:00,18:00';
        \assert(\is_string($slots));
        return \array_map(trim(...), \explode(',', $slots));
    }

    public function getHarmonizationTolerance(): int
    {
        $harmonization = $this->config['harmonization'] ?? [];
        \assert(\is_array($harmonization));
        $tolerance = $harmonization['tolerance'] ?? 3600;
        \assert(\is_int($tolerance) || \is_string($tolerance));
        return (int)$tolerance;
    }

    public function isAutoRoundEnabled(): bool
    {
        $harmonization = $this->config['harmonization'] ?? [];
        \assert(\is_array($harmonization));
        return (bool)($harmonization['auto_round'] ?? false);
    }

    // Advanced Configuration

    public function getDefaultMaxLifetime(): int
    {
        $advanced = $this->config['advanced'] ?? [];
        \assert(\is_array($advanced));
        $lifetime = $advanced['default_max_lifetime'] ?? 86400;
        \assert(\is_int($lifetime) || \is_string($lifetime));
        return (int)$lifetime;
    }

    public function isDebugLoggingEnabled(): bool
    {
        $advanced = $this->config['advanced'] ?? [];
        \assert(\is_array($advanced));
        return (bool)($advanced['debug_logging'] ?? false);
    }

    // Convenience Methods

    public function isPerContentScoping(): bool
    {
        return $this->getScopingStrategy() === 'per-content';
    }

    public function isSchedulerTiming(): bool
    {
        return $this->getTimingStrategy() === 'scheduler';
    }

    public function isHybridTiming(): bool
    {
        return $this->getTimingStrategy() === 'hybrid';
    }

    public function isDynamicTiming(): bool
    {
        return $this->getTimingStrategy() === 'dynamic';
    }

    /**
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        return $this->config;
    }
}
