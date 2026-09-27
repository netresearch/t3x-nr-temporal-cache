<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Functional\Controller\Backend;

use InvalidArgumentException;
use Netresearch\TemporalCache\Controller\Backend\TemporalCacheController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Pins the markup of the backend module views to TYPO3 core conventions, so the
 * module follows the backend's light and dark scheme:
 *
 * - the views render inside core's `Module` layout, which carries the doc header
 *   (an extension-level `Layouts/Module.html` replaces it and drops the header);
 * - badges and info boxes use core classes whose colours follow the scheme, not
 *   Bootstrap classes that keep one colour in both schemes (`bg-*`, `.alert`).
 *   `f:link.action` renders no `<a>` without a routed module request, so the
 *   classes on the module's links are pinned by the unit test
 *   BackendTemplateClassesTest instead;
 * - headings do not skip levels and each view has one h1.
 */
#[CoversClass(TemporalCacheController::class)]
final class TemporalCacheModuleMarkupTest extends FunctionalTestCase
{
    use BackendModuleRequestTrait;

    protected array $coreExtensionsToLoad = ['scheduler', 'reports'];

    protected array $testExtensionsToLoad = [
        'nr_temporal_cache',
    ];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'nr_temporal_cache' => [
                'scoping' => [
                    'strategy' => 'per-content',
                    'use_refindex' => true,
                ],
                'timing' => [
                    'strategy' => 'dynamic',
                ],
                'harmonization' => [
                    'enabled' => true,
                    'slots' => '00:00,06:00,12:00,18:00',
                    'tolerance' => 3600,
                ],
            ],
        ],
    ];

    private TemporalCacheController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/be_users.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/tt_content.csv');
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($GLOBALS['BE_USER']);
        $GLOBALS['TYPO3_REQUEST'] = $this->createModuleRequest('dashboard');

        $this->controller = $this->get(TemporalCacheController::class);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function viewProvider(): array
    {
        return [
            'dashboard' => ['dashboard', ''],
            'content' => ['content', ''],
            'wizard welcome' => ['wizard', 'welcome'],
            'wizard analysis' => ['wizard', 'analysis'],
            'wizard presets' => ['wizard', 'presets'],
            'wizard custom' => ['wizard', 'custom'],
            'wizard summary' => ['wizard', 'summary'],
        ];
    }

    #[Test]
    #[DataProvider('viewProvider')]
    public function viewRendersInsideCoreModuleLayoutWithDocHeader(string $action, string $step): void
    {
        $body = $this->render($action, $step);

        self::assertStringContainsString('module-docheader', $body, 'core Module layout renders the doc header');
        self::assertStringContainsString('t3js-module-body', $body, 'core Module layout wraps the view');
        self::assertStringNotContainsString('data-module-name="temporal-cache"', $body, "the extension layout must not replace core's");
    }

    /**
     * @return array<string, array{string}>
     */
    public static function actionProvider(): array
    {
        return [
            'dashboard' => ['dashboard'],
            'content' => ['content'],
            'wizard' => ['wizard'],
        ];
    }

    /**
     * TYPO3 14 adds its own reload and bookmark buttons, so the controller must not
     * add them there; 12 and 13 need the controller's. Either way the doc header
     * carries exactly one of each. One render per test: on 14 the DocHeaderComponent
     * is a shared service, so a second render in the same process sees the first
     * render's buttons.
     */
    #[Test]
    #[DataProvider('actionProvider')]
    public function docHeaderHasExactlyOneReloadAndOneBookmarkButton(string $action): void
    {
        $body = $this->render($action, 'welcome');

        self::assertSame(1, \substr_count($body, 'data-identifier="actions-refresh"'), 'reload buttons');
        self::assertSame(
            1,
            \substr_count($body, 'data-dispatch-action="TYPO3.ShortcutMenu.createShortcut"')
                + \substr_count($body, '<typo3-backend-bookmark-button '),
            'bookmark buttons (12/13: shortcut dispatch link, 14: bookmark element)'
        );
    }

    #[Test]
    public function viewMenuHasItsOwnLabel(): void
    {
        $body = $this->render('content', '');

        // 12/13 render the menu as a <select> with a visually hidden <label>; 14 as a
        // dropdown button whose visually hidden prefix is the menu label, or the first
        // item's title when no label is set.
        self::assertMatchesRegularExpression(
            '#<label class="form-label visually-hidden" for="temporal_cache_menu">View</label>|<span class="visually-hidden">View:</span>#',
            $body
        );
    }

    #[Test]
    #[DataProvider('viewProvider')]
    public function viewUsesOnlySchemeAwareCoreClasses(string $action, string $step): void
    {
        $body = $this->render($action, $step);

        self::assertDoesNotMatchRegularExpression('/\bbtn-outline-[a-z]+/', $body, 'core has no btn-outline-* classes');
        self::assertDoesNotMatchRegularExpression('/\bbtn-secondary\b/', $body, 'btn-secondary keeps one fill in both schemes');
        self::assertDoesNotMatchRegularExpression('/class="[^"]*\bbadge\b[^"]*\bbg-[a-z]+/', $body, 'badges use badge-*, not bg-*');
        self::assertDoesNotMatchRegularExpression('/class="[^"]*\balert\b/', $body, 'info boxes use core callouts, not .alert');
        self::assertDoesNotMatchRegularExpression('/\btext-white\b/', $body);
        self::assertDoesNotMatchRegularExpression('/\btable-responsive\b/', $body, 'core wraps tables in table-fit');
    }

    #[Test]
    #[DataProvider('viewProvider')]
    public function headingsDoNotSkipLevels(string $action, string $step): void
    {
        $body = $this->render($action, $step);
        $start = \strpos($body, 't3js-module-body');
        self::assertIsInt($start);

        \preg_match_all('/<h([1-6])\b/', \substr($body, $start), $matches);
        $levels = \array_map(intval(...), $matches[1]);

        self::assertSame(1, \count(\array_keys($levels, 1, true)), 'exactly one h1 per view');
        self::assertSame(1, $levels[0] ?? null, 'the view starts with its h1');
        $previous = 0;
        foreach ($levels as $level) {
            self::assertLessThanOrEqual($previous + 1, $level, 'heading levels ' . \implode(',', $levels) . ' skip a level');
            $previous = $level;
        }
    }

    #[Test]
    public function contentListUsesCoreBadgesAndTableWrapper(): void
    {
        $body = $this->render('content', '');

        self::assertMatchesRegularExpression('/class="badge badge-(primary|default)"/', $body);
        self::assertStringContainsString('class="table-fit"', $body);
    }

    #[Test]
    public function dashboardUsesCoreBadges(): void
    {
        $body = $this->render('dashboard', '');

        self::assertMatchesRegularExpression('/class="badge badge-primary"/', $body);
        self::assertMatchesRegularExpression('/class="badge badge-info"/', $body);
    }

    #[Test]
    public function dashboardHarmonizationHintIsInfoCallout(): void
    {
        // 20 minutes after the 06:00 slot, inside the 3600 s tolerance: one
        // harmonizable candidate, which is what makes the dashboard show the hint.
        $this->getConnectionPool()->getConnectionForTable('tt_content')->insert('tt_content', [
            'uid' => 9100,
            'pid' => 1,
            'header' => 'Harmonizable teaser',
            'CType' => 'text',
            'starttime' => \strtotime('tomorrow 06:20'),
        ]);

        $body = $this->render('dashboard', '');

        self::assertMatchesRegularExpression(
            '#<div class="callout callout-info">(?:(?!<div class="callout ).)*can be harmonized#s',
            $body
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function calloutProvider(): array
    {
        return [
            'welcome statistics' => ['welcome', 'callout callout-info'],
            'custom note' => ['custom', 'callout callout-warning'],
            'summary' => ['summary', 'callout callout-success'],
        ];
    }

    #[Test]
    #[DataProvider('calloutProvider')]
    public function wizardUsesCoreCallouts(string $step, string $calloutClass): void
    {
        $body = $this->render('wizard', $step);

        self::assertStringContainsString('class="' . $calloutClass . '"', $body);
    }

    #[Test]
    public function wizardSummaryHeaderHasNoFixedColours(): void
    {
        $body = $this->render('wizard', 'summary');

        self::assertDoesNotMatchRegularExpression('/card-header[^"]*\bbg-success\b/', $body);
    }

    private function render(string $action, string $step): string
    {
        $request = $this->createModuleRequest('dashboard');
        $response = match ($action) {
            'dashboard' => $this->controller->dashboardAction($request),
            'content' => $this->controller->contentAction($request),
            'wizard' => $this->controller->wizardAction($request, $step),
            default => throw new InvalidArgumentException($action, 1790000001),
        };
        self::assertSame(200, $response->getStatusCode());

        return (string)$response->getBody();
    }
}
