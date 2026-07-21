<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Configuration;

use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for Configuration/JavaScriptModules.php
 *
 * Guards against the backend module 500 error
 * "#1728220800: JavaScript module '@netresearch/nr-temporal-cache/backend-module.js'
 * could not be resolved" by asserting that the import map declares the
 * specifier prefix used by TemporalCacheController and that the mapped
 * module file actually exists.
 */
final class JavaScriptModulesConfigurationTest extends UnitTestCase
{
    private const SPECIFIER_PREFIX = '@netresearch/nr-temporal-cache/';

    /**
     * Specifier loaded via PageRenderer::loadJavaScriptModule() in
     * TemporalCacheController::setupModuleTemplate().
     */
    private const MODULE_SPECIFIER = '@netresearch/nr-temporal-cache/backend-module.js';

    private string $extensionRoot;

    /**
     * Cached across tests: require_once returns the configuration array only
     * on the first inclusion (and bool true afterwards).
     *
     * @var array{dependencies?: list<string>, imports?: array<string, string>}|null
     */
    private static ?array $cachedConfiguration = null;

    /**
     * @var array{dependencies?: list<string>, imports?: array<string, string>}
     */
    private array $configuration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extensionRoot = \dirname(__DIR__, 3);

        if (self::$cachedConfiguration === null) {
            /** @var array{dependencies?: list<string>, imports?: array<string, string>} $configuration */
            $configuration = require_once $this->extensionRoot . '/Configuration/JavaScriptModules.php';
            self::$cachedConfiguration = $configuration;
        }

        $this->configuration = self::$cachedConfiguration;
    }

    public function testDependsOnBackend(): void
    {
        self::assertArrayHasKey('dependencies', $this->configuration);
        self::assertContains('backend', $this->configuration['dependencies'] ?? []);
    }

    public function testImportMapDeclaresSpecifierPrefix(): void
    {
        self::assertArrayHasKey('imports', $this->configuration);
        self::assertArrayHasKey(self::SPECIFIER_PREFIX, $this->configuration['imports'] ?? []);
    }

    public function testSpecifierPrefixMapsToPublicJavaScriptDirectory(): void
    {
        $target = $this->configuration['imports'][self::SPECIFIER_PREFIX] ?? '';

        self::assertSame('EXT:nr_temporal_cache/Resources/Public/JavaScript/', $target);
        self::assertDirectoryExists($this->extensionRoot . '/Resources/Public/JavaScript');
    }

    public function testControllerModuleSpecifierResolvesToExistingFile(): void
    {
        $target = $this->configuration['imports'][self::SPECIFIER_PREFIX] ?? '';
        $relativePath = \str_replace('EXT:nr_temporal_cache/', '', $target)
            . \substr(self::MODULE_SPECIFIER, \strlen(self::SPECIFIER_PREFIX));

        self::assertFileExists($this->extensionRoot . '/' . $relativePath);
    }
}
