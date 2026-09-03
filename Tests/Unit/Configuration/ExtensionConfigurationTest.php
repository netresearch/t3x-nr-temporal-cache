<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Configuration;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration as Typo3ExtensionConfiguration;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for ExtensionConfiguration
 */
#[CoversClass(ExtensionConfiguration::class)]
#[UsesClass(TemporalContent::class)]
final class ExtensionConfigurationTest extends UnitTestCase
{
    private Typo3ExtensionConfiguration&Stub $typo3ExtensionConfiguration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->typo3ExtensionConfiguration = $this->createStub(Typo3ExtensionConfiguration::class);
    }

    /**     */
    public function testConstructorLoadsConfiguration(): void
    {
        $config = [
            'scoping' => ['strategy' => 'per-content'],
            'timing' => ['strategy' => 'scheduler'],
        ];

        /** @var Typo3ExtensionConfiguration&MockObject $typo3ExtensionConfiguration */
        $typo3ExtensionConfiguration = $this->createMock(Typo3ExtensionConfiguration::class);
        $typo3ExtensionConfiguration
            ->expects(self::once())
            ->method('get')
            ->with('nr_temporal_cache')
            ->willReturn($config);

        $subject = new ExtensionConfiguration($typo3ExtensionConfiguration);

        self::assertSame('per-content', $subject->getScopingStrategy());
        self::assertSame('scheduler', $subject->getTimingStrategy());
    }

    /**     */
    public function testConstructorHandlesEmptyConfiguration(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn(null);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        // Should return defaults
        self::assertSame('global', $subject->getScopingStrategy());
        self::assertSame('dynamic', $subject->getTimingStrategy());
    }

    #[DataProvider('scopingStrategyDataProvider')]
    public function testGetScopingStrategyReturnsConfiguredValue(string $strategy): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn(['scoping' => ['strategy' => $strategy]]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertSame($strategy, $subject->getScopingStrategy());
    }

    public static function scopingStrategyDataProvider(): array
    {
        return [
            'global' => ['global'],
            'per-page' => ['per-page'],
            'per-content' => ['per-content'],
        ];
    }

    /**     */
    public function testGetScopingStrategyReturnsDefaultWhenNotConfigured(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn([]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertSame('global', $subject->getScopingStrategy());
    }

    #[DataProvider('booleanDataProvider')]
    public function testUseRefindexReturnsBooleanValue(mixed $value, bool $expected): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn(['scoping' => ['use_refindex' => $value]]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertSame($expected, $subject->useRefindex());
    }

    public static function booleanDataProvider(): array
    {
        return [
            'true' => [true, true],
            'false' => [false, false],
            '1' => [1, true],
            '0' => [0, false],
            'string true' => ['1', true],
            'string false' => ['0', false],
        ];
    }

    /**     */
    public function testUseRefindexReturnsTrueByDefault(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn([]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertTrue($subject->useRefindex());
    }

    #[DataProvider('timingStrategyDataProvider')]
    public function testGetTimingStrategyReturnsConfiguredValue(string $strategy): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn(['timing' => ['strategy' => $strategy]]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertSame($strategy, $subject->getTimingStrategy());
    }

    public static function timingStrategyDataProvider(): array
    {
        return [
            'dynamic' => ['dynamic'],
            'scheduler' => ['scheduler'],
            'hybrid' => ['hybrid'],
        ];
    }

    /**     */
    public function testGetTimingStrategyReturnsDefaultWhenNotConfigured(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn([]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertSame('dynamic', $subject->getTimingStrategy());
    }

    /**     */
    public function testGetTimingRulesReturnsConfiguredValues(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn([
                'timing' => [
                    'hybrid' => [
                        'pages' => 'dynamic',
                        'content' => 'scheduler',
                    ],
                ],
            ]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);
        $rules = $subject->getTimingRules();

        self::assertSame('dynamic', $rules['page']);
        self::assertSame('scheduler', $rules['content']);
    }

    /**
     * getTimingRules() is consumed by HybridTimingStrategy as
     * $timingRules[$content->getContentType()]. If the key set here drifts from what
     * getContentType() emits, the lookup misses and the strategy silently falls back
     * to 'dynamic' — which is exactly how timing.hybrid.pages became dead configuration
     * (the array was keyed 'pages' while getContentType() yields 'page').
     */
    public function testGetTimingRulesIsKeyedByTheContentTypesTemporalContentEmits(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn([
                'timing' => [
                    'hybrid' => [
                        'pages' => 'scheduler',
                        'content' => 'dynamic',
                    ],
                ],
            ]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);
        $rules = $subject->getTimingRules();

        $page = new TemporalContent(1, 'pages', 'A page', 0, null, null, 0, 0);
        $content = new TemporalContent(2, 'tt_content', 'An element', 1, null, null, 0, 0);

        self::assertArrayHasKey($page->getContentType(), $rules);
        self::assertArrayHasKey($content->getContentType(), $rules);
        self::assertSame('scheduler', $rules[$page->getContentType()]);
        self::assertSame('dynamic', $rules[$content->getContentType()]);
    }

    /**     */
    public function testGetTimingRulesReturnsDefaults(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn([]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);
        $rules = $subject->getTimingRules();

        self::assertSame('dynamic', $rules['page']);
        self::assertSame('scheduler', $rules['content']);
    }

    /**     */
    public function testIsHarmonizationEnabledReturnsConfiguredValue(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn(['harmonization' => ['enabled' => true]]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertTrue($subject->isHarmonizationEnabled());
    }

    /**     */
    public function testIsHarmonizationEnabledReturnsFalseByDefault(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn([]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertFalse($subject->isHarmonizationEnabled());
    }

    /**     */
    public function testGetHarmonizationSlotsReturnsConfiguredValues(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn(['harmonization' => ['slots' => '00:00,08:00,16:00']]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);
        $slots = $subject->getHarmonizationSlots();

        self::assertSame(['00:00', '08:00', '16:00'], $slots);
    }

    /**     */
    public function testGetHarmonizationSlotsReturnsDefaults(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn([]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);
        $slots = $subject->getHarmonizationSlots();

        self::assertSame(['00:00', '06:00', '12:00', '18:00'], $slots);
    }

    /**     */
    public function testGetHarmonizationSlotsTrimsWhitespace(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn(['harmonization' => ['slots' => ' 00:00 , 12:00 , 18:00 ']]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);
        $slots = $subject->getHarmonizationSlots();

        self::assertSame(['00:00', '12:00', '18:00'], $slots);
    }

    /**     */
    public function testGetHarmonizationToleranceReturnsConfiguredValue(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn(['harmonization' => ['tolerance' => 7200]]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertSame(7200, $subject->getHarmonizationTolerance());
    }

    /**     */
    public function testGetHarmonizationToleranceReturnsDefault(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn([]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertSame(3600, $subject->getHarmonizationTolerance());
    }

    /**     */
    public function testIsAutoRoundEnabledReturnsConfiguredValue(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn(['harmonization' => ['auto_round' => true]]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertTrue($subject->isAutoRoundEnabled());
    }

    /**     */
    public function testIsAutoRoundEnabledReturnsFalseByDefault(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn([]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertFalse($subject->isAutoRoundEnabled());
    }

    /**     */
    public function testGetDefaultMaxLifetimeReturnsConfiguredValue(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn(['advanced' => ['default_max_lifetime' => 172800]]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertSame(172800, $subject->getDefaultMaxLifetime());
    }

    /**     */
    public function testGetDefaultMaxLifetimeReturnsDefault(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn([]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertSame(86400, $subject->getDefaultMaxLifetime());
    }

    /**     */
    public function testIsDebugLoggingEnabledReturnsConfiguredValue(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn(['advanced' => ['debug_logging' => true]]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertTrue($subject->isDebugLoggingEnabled());
    }

    /**     */
    public function testIsDebugLoggingEnabledReturnsFalseByDefault(): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn([]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertFalse($subject->isDebugLoggingEnabled());
    }

    #[DataProvider('convenienceMethodDataProvider')]
    public function testConvenienceMethodsWorkCorrectly(string $method, string $configKey, string $configValue, bool $expected): void
    {
        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn([$configKey => ['strategy' => $configValue]]);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertSame($expected, $subject->$method());
    }

    public static function convenienceMethodDataProvider(): array
    {
        return [
            'isPerContentScoping true' => ['isPerContentScoping', 'scoping', 'per-content', true],
            'isPerContentScoping false' => ['isPerContentScoping', 'scoping', 'global', false],
            'isSchedulerTiming true' => ['isSchedulerTiming', 'timing', 'scheduler', true],
            'isSchedulerTiming false' => ['isSchedulerTiming', 'timing', 'dynamic', false],
            'isHybridTiming true' => ['isHybridTiming', 'timing', 'hybrid', true],
            'isHybridTiming false' => ['isHybridTiming', 'timing', 'scheduler', false],
            'isDynamicTiming true' => ['isDynamicTiming', 'timing', 'dynamic', true],
            'isDynamicTiming false' => ['isDynamicTiming', 'timing', 'hybrid', false],
        ];
    }

    /**     */
    public function testGetAllReturnsCompleteConfiguration(): void
    {
        $config = [
            'scoping' => ['strategy' => 'per-content'],
            'timing' => ['strategy' => 'scheduler'],
            'harmonization' => ['enabled' => true],
        ];

        $this->typo3ExtensionConfiguration
            ->method('get')
            ->willReturn($config);

        $subject = new ExtensionConfiguration($this->typo3ExtensionConfiguration);

        self::assertSame($config, $subject->getAll());
    }
}
