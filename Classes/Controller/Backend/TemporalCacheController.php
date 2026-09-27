<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Controller\Backend;

use Netresearch\TemporalCache\Configuration\ExtensionConfiguration;
use Netresearch\TemporalCache\Domain\Model\TemporalContent;
use Netresearch\TemporalCache\Domain\Repository\TemporalContentRepository;
use Netresearch\TemporalCache\Service\Backend\HarmonizationAnalysisService;
use Netresearch\TemporalCache\Service\Backend\PermissionService;
use Netresearch\TemporalCache\Service\Backend\TemporalCacheStatisticsService;
use Netresearch\TemporalCache\Service\HarmonizationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder as BackendUriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\Buttons\LinkButton;
use TYPO3\CMS\Backend\Template\Components\Menu\Menu;
use TYPO3\CMS\Backend\Template\Components\Menu\MenuItem;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Backend module controller for Temporal Cache management.
 *
 * Provides three main views:
 * - Dashboard: Statistics, timeline visualization, KPIs
 * - Content: List of temporal content with harmonization suggestions
 * - Wizard: Configuration wizard with presets
 *
 * Refactored in Phase 3 to follow SOLID principles:
 * - Statistics logic extracted to TemporalCacheStatisticsService
 * - Harmonization analysis extracted to HarmonizationAnalysisService
 * - Controller now focuses on request handling and view rendering
 */
#[AsController]
final class TemporalCacheController extends ActionController
{
    private const ITEMS_PER_PAGE = 50;

    private const MODULE_ROUTE = 'tools_TemporalCache';

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly TemporalContentRepository $contentRepository,
        private readonly TemporalCacheStatisticsService $statisticsService,
        private readonly HarmonizationAnalysisService $harmonizationAnalysisService,
        private readonly HarmonizationService $harmonizationService,
        private readonly PermissionService $permissionService,
        private readonly CacheManager $cacheManager,
        private readonly IconFactory $iconFactory,
        private readonly PageRenderer $pageRenderer,
        private readonly BackendUriBuilder $backendUriBuilder,
    ) {
    }

    /**
     * Build a backend module URI for the given action.
     *
     * Uses TYPO3's Backend UriBuilder (routing-based) instead of Extbase's
     * UriBuilder which can return empty strings in v14 backend module context,
     * causing "LinkButton is not valid" errors.
     */
    private function buildModuleUri(string $action): string
    {
        try {
            return (string)$this->backendUriBuilder->buildUriFromRoute(
                self::MODULE_ROUTE,
                ['action' => $action]
            );
        } catch (Throwable) {
            return '#';
        }
    }

    /**
     * Dashboard action: Show statistics, timeline, and KPIs.
     */
    public function dashboardAction(?ServerRequestInterface $request = null): ResponseInterface
    {
        $request ??= $this->request;
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $this->setupModuleTemplate($moduleTemplate, 'dashboard');

        $currentTime = \time();
        $stats = $this->statisticsService->calculateStatistics($currentTime);
        $timeline = $this->statisticsService->buildTimeline($currentTime);
        $config = $this->statisticsService->getConfigurationSummary();

        $moduleTemplate->assignMultiple([
            'stats' => $stats,
            'timeline' => $timeline,
            'config' => $config,
            'currentTime' => $currentTime,
        ]);

        return $moduleTemplate->renderResponse('Backend/TemporalCache/Dashboard');
    }

    /**
     * Content action: List all temporal content with harmonization suggestions.
     */
    public function contentAction(?ServerRequestInterface $request = null, int $currentPage = 1, string $filter = 'all'): ResponseInterface
    {
        $request ??= $this->request;
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $this->setupModuleTemplate($moduleTemplate, 'content');

        $currentTime = \time();
        $allContent = $this->contentRepository->findAllWithTemporalFields();
        $filteredContent = $this->filterContent($allContent, $filter, $currentTime);

        // Add harmonization suggestions and pre-computed visibility status
        /** @var array<int, array<string, mixed>> $contentWithSuggestions */
        $contentWithSuggestions = \array_map(
            function (TemporalContent $content) use ($currentTime): array {
                $suggestion = $this->harmonizationAnalysisService->generateHarmonizationSuggestion($content);
                // Pre-compute isVisible for Fluid template (can't call methods with params in Fluid)
                $suggestion['isVisible'] = $content->isVisible($currentTime);
                return $suggestion;
            },
            $filteredContent
        );

        // Pagination
        $paginator = new ArrayPaginator($contentWithSuggestions, $currentPage, self::ITEMS_PER_PAGE);
        $pagination = new SimplePagination($paginator);

        $moduleTemplate->assignMultiple([
            'content' => $paginator->getPaginatedItems(),
            'pagination' => $pagination,
            'paginator' => $paginator,
            'filter' => $filter,
            'filterOptions' => $this->getFilterOptions(),
            'currentTime' => $currentTime,
            'harmonizationEnabled' => $this->extensionConfiguration->isHarmonizationEnabled(),
            'canModifyContent' => $this->permissionService->canModifyTemporalContent(),
            'permissionStatus' => $this->permissionService->getPermissionStatus(),
            'harmonizeActionUri' => $this->buildModuleUri('harmonize'),
        ]);

        return $moduleTemplate->renderResponse('Backend/TemporalCache/Content');
    }

    /**
     * Wizard action: Configuration wizard with presets.
     */
    public function wizardAction(?ServerRequestInterface $request = null, string $step = 'welcome'): ResponseInterface
    {
        $request ??= $this->request;
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $this->setupModuleTemplate($moduleTemplate, 'wizard');

        $currentConfig = $this->extensionConfiguration->getAll();
        $presets = $this->getConfigurationPresets();
        $recommendations = $this->analyzeConfiguration();

        $moduleTemplate->assignMultiple([
            'step' => $step,
            'currentConfig' => $currentConfig,
            'presets' => $presets,
            'recommendations' => $recommendations,
            'stats' => $this->statisticsService->calculateStatistics(\time()),
        ]);

        return $moduleTemplate->renderResponse('Backend/TemporalCache/Wizard');
    }

    /**
     * Harmonize action: Apply bulk harmonization to content.
     */
    public function harmonizeAction(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();
        \assert(\is_array($parsedBody));
        $contentUids = $parsedBody['content'] ?? [];
        \assert(\is_array($contentUids));
        $dryRun = (bool)($parsedBody['dryRun'] ?? true);

        if ($contentUids === []) {
            $json = \json_encode([
                'success' => false,
                'message' => $this->getLanguageService()->sL('LLL:EXT:nr_temporal_cache/Resources/Private/Language/locallang_mod.xlf:harmonize.error.no_content'),
            ]);
            \assert(\is_string($json));
            return $this->jsonResponse($json);
        }

        if (!$this->extensionConfiguration->isHarmonizationEnabled()) {
            $json = \json_encode([
                'success' => false,
                'message' => $this->getLanguageService()->sL('LLL:EXT:nr_temporal_cache/Resources/Private/Language/locallang_mod.xlf:harmonize.error.disabled'),
            ]);
            \assert(\is_string($json));
            return $this->jsonResponse($json);
        }

        // Check write permissions
        if (!$this->permissionService->canModifyTemporalContent()) {
            $unmodifiableTables = $this->permissionService->getUnmodifiableTables();
            $json = \json_encode([
                'success' => false,
                'message' => \sprintf(
                    $this->getLanguageService()->sL('LLL:EXT:nr_temporal_cache/Resources/Private/Language/locallang_mod.xlf:harmonize.error.no_permission'),
                    \implode(', ', $unmodifiableTables)
                ),
            ]);
            \assert(\is_string($json));
            return $this->jsonResponse($json);
        }

        $results = [];
        foreach ($contentUids as $item) {
            // Each selected item may be either a plain uid (legacy, assumed tt_content)
            // or an object carrying both uid and table so pages and content are routed
            // to the correct table. findByUid() rejects tables not registered for monitoring.
            if (\is_array($item)) {
                $rawUid = $item['uid'] ?? 0;
                $rawTable = $item['table'] ?? null;
                $tableName = \is_string($rawTable) ? $rawTable : 'tt_content';
            } else {
                $rawUid = $item;
                $tableName = 'tt_content';
            }

            $uid = match (true) {
                \is_int($rawUid) => $rawUid,
                \is_string($rawUid) && \is_numeric($rawUid) => (int)$rawUid,
                default => 0,
            };

            if ($uid <= 0) {
                continue;
            }

            $content = $this->contentRepository->findByUid($uid, $tableName);
            if (!$content instanceof TemporalContent) {
                continue;
            }

            $result = $this->harmonizationService->harmonizeContent($content, $dryRun);
            $results[] = $result;
        }

        $successCount = \count(\array_filter($results, fn (array $r): bool => $r['success']));
        $totalCount = \count($results);

        if (!$dryRun) {
            // Clear page cache after harmonization
            $this->cacheManager->flushCachesInGroup('pages');
        }

        $json = \json_encode([
            'success' => true,
            'message' => \sprintf(
                $this->getLanguageService()->sL('LLL:EXT:nr_temporal_cache/Resources/Private/Language/locallang_mod.xlf:harmonize.success'),
                $successCount,
                $totalCount
            ),
            'results' => $results,
            'dryRun' => $dryRun,
        ]);
        \assert(\is_string($json));
        return $this->jsonResponse($json);
    }

    /**
     * Setup module template with menu and common settings.
     */
    private function setupModuleTemplate(ModuleTemplate $moduleTemplate, string $currentAction): void
    {
        $moduleTemplate->setTitle(
            $this->getLanguageService()->sL('LLL:EXT:nr_temporal_cache/Resources/Private/Language/locallang_mod.xlf:mlang_tabs_tab')
        );

        // Load JavaScript module (TYPO3 v12/v13 compatible via injected PageRenderer)
        $this->pageRenderer->loadJavaScriptModule(
            '@netresearch/nr-temporal-cache/backend-module.js'
        );

        // Add DocHeader buttons (may fail in test environments)
        try {
            $this->addDocHeaderButtons($moduleTemplate, $currentAction);
        } catch (Throwable) {
            // Gracefully skip button creation in test/CLI environments
        }

        // Create module menu. Menu, menu items and link buttons are created with
        // makeInstance(): that is what MenuRegistry::makeMenu(), Menu::makeMenuItem()
        // and ButtonBar::makeLinkButton() do on 12.4 and 13.4, and what
        // ComponentFactory::create*() does on 14.3, where the make*() methods are
        // deprecated (#107823). ComponentFactory itself does not exist before 14.
        try {
            $menu = GeneralUtility::makeInstance(Menu::class);
            $menu->setIdentifier('temporal_cache_menu');

            $actions = ['dashboard', 'content', 'wizard'];
            foreach ($actions as $action) {
                $item = GeneralUtility::makeInstance(MenuItem::class)
                    ->setTitle($this->getLanguageService()->sL(
                        'LLL:EXT:nr_temporal_cache/Resources/Private/Language/locallang_mod.xlf:menu.' . $action
                    ))
                    ->setHref($this->buildModuleUri($action))
                    ->setActive($currentAction === $action);
                $menu->addMenuItem($item);
            }

            $moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
        } catch (Throwable) {
            // Gracefully skip menu creation in test/CLI environments
        }
    }

    /**
     * Add DocHeader buttons to module template.
     */
    private function addDocHeaderButtons(ModuleTemplate $moduleTemplate, string $currentAction): void
    {
        $docHeader = $moduleTemplate->getDocHeaderComponent();
        $buttonBar = $docHeader->getButtonBar();
        $displayName = $this->getLanguageService()->sL('LLL:EXT:nr_temporal_cache/Resources/Private/Language/locallang_mod.xlf:mlang_tabs_tab');

        if (\method_exists($docHeader, 'setShortcutContext')) {
            // TYPO3 v14 adds the reload and shortcut buttons itself; adding them
            // here as well shows two reload buttons, and a manual shortcut button
            // is deprecated there.
            $docHeader->setShortcutContext(self::MODULE_ROUTE, $displayName, ['action' => $currentAction]);
        } else {
            // Refresh button (all actions)
            $refreshButton = GeneralUtility::makeInstance(LinkButton::class)
                ->setHref($this->buildModuleUri($currentAction))
                ->setTitle($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.reload'))
                ->setIcon($this->iconFactory->getIcon('actions-refresh', \class_exists(IconSize::class) ? IconSize::SMALL : Icon::SIZE_SMALL))
                ->setShowLabelText(false);
            $buttonBar->addButton($refreshButton, ButtonBar::BUTTON_POSITION_RIGHT, 1);

            // Shortcut button (all actions)
            $shortcutButton = $buttonBar->makeShortcutButton()
                ->setRouteIdentifier(self::MODULE_ROUTE)
                ->setDisplayName($displayName)
                ->setArguments(['action' => $currentAction]);
            $buttonBar->addButton($shortcutButton, ButtonBar::BUTTON_POSITION_RIGHT, 2);
        }

        // Action-specific buttons
        switch ($currentAction) {
            case 'dashboard':
                // Quick access to content list
                $contentButton = GeneralUtility::makeInstance(LinkButton::class)
                    ->setHref($this->buildModuleUri('content'))
                    ->setTitle($this->getLanguageService()->sL('LLL:EXT:nr_temporal_cache/Resources/Private/Language/locallang_mod.xlf:button.view_content'))
                    ->setIcon($this->iconFactory->getIcon('actions-document-open', \class_exists(IconSize::class) ? IconSize::SMALL : Icon::SIZE_SMALL))
                    ->setShowLabelText(true);
                $buttonBar->addButton($contentButton, ButtonBar::BUTTON_POSITION_LEFT, 1);
                break;

            case 'content':
                // Filter display only - harmonize button is in template
                break;

            case 'wizard':
                // Help button removed (not available in TYPO3 v13 ButtonBar API)
                break;
        }
    }

    /**
     * Filter content based on selected filter.
     *
     * @param array<TemporalContent> $content
     * @return array<TemporalContent>
     */
    private function filterContent(array $content, string $filter, int $currentTime): array
    {
        return match ($filter) {
            'pages' => \array_filter($content, fn (TemporalContent $c): bool => $c->isPage()),
            'content' => \array_filter($content, fn (TemporalContent $c): bool => $c->isContent()),
            'active' => \array_filter($content, fn (TemporalContent $c): bool => $c->isVisible($currentTime)),
            'scheduled' => \array_filter($content, fn (TemporalContent $c): bool => $c->starttime !== null && $c->starttime > $currentTime),
            'expired' => \array_filter($content, fn (TemporalContent $c): bool => $c->endtime !== null && $c->endtime < $currentTime),
            'harmonizable' => $this->harmonizationAnalysisService->filterHarmonizableContent($content),
            default => $content,
        };
    }

    /**
     * Get available filter options.
     *
     * @return array<string, string>
     */
    private function getFilterOptions(): array
    {
        return [
            'all' => 'filter.all',
            'pages' => 'filter.pages',
            'content' => 'filter.content',
            'active' => 'filter.active',
            'scheduled' => 'filter.scheduled',
            'expired' => 'filter.expired',
            'harmonizable' => 'filter.harmonizable',
        ];
    }


    /**
     * Get configuration presets for wizard.
     *
     * @return array<string, array<string, mixed>>
     */
    private function getConfigurationPresets(): array
    {
        return [
            'simple' => [
                'name' => 'preset.simple.name',
                'description' => 'preset.simple.description',
                'config' => [
                    'scoping' => ['strategy' => 'global'],
                    'timing' => ['strategy' => 'dynamic'],
                    'harmonization' => ['enabled' => false],
                ],
            ],
            'balanced' => [
                'name' => 'preset.balanced.name',
                'description' => 'preset.balanced.description',
                'config' => [
                    'scoping' => ['strategy' => 'per-page'],
                    'timing' => ['strategy' => 'hybrid'],
                    'harmonization' => ['enabled' => true, 'slots' => '00:00,06:00,12:00,18:00'],
                ],
            ],
            'aggressive' => [
                'name' => 'preset.aggressive.name',
                'description' => 'preset.aggressive.description',
                'config' => [
                    'scoping' => ['strategy' => 'per-content', 'use_refindex' => true],
                    'timing' => ['strategy' => 'scheduler'],
                    'harmonization' => ['enabled' => true, 'slots' => '00:00,04:00,08:00,12:00,16:00,20:00'],
                ],
            ],
        ];
    }

    /**
     * Analyze current configuration and provide recommendations.
     *
     * `state` is the integer severity the `f:be.infobox` ViewHelper takes on
     * every supported TYPO3 version (v14 also accepts the enum, v12/v13 do not).
     *
     * @return array<int, array{type: string, state: int, title: string, message: string}>
     */
    private function analyzeConfiguration(): array
    {
        $recommendations = [];
        $stats = $this->statisticsService->calculateStatistics(\time());

        // Recommendation: Enable harmonization if many transitions
        if (!$this->extensionConfiguration->isHarmonizationEnabled() && $stats['transitionsPerDay'] > 10) {
            $recommendations[] = [
                'type' => 'warning',
                'state' => ContextualFeedbackSeverity::WARNING->value,
                'title' => 'recommendation.harmonization.title',
                'message' => 'recommendation.harmonization.message',
            ];
        }

        // Recommendation: Use per-content scoping if many content elements
        if ($this->extensionConfiguration->getScopingStrategy() === 'global' && $stats['contentCount'] > 100) {
            $recommendations[] = [
                'type' => 'info',
                'state' => ContextualFeedbackSeverity::INFO->value,
                'title' => 'recommendation.scoping.title',
                'message' => 'recommendation.scoping.message',
            ];
        }

        // Recommendation: Use scheduler timing if many transitions per day
        if ($this->extensionConfiguration->getTimingStrategy() === 'dynamic' && $stats['transitionsPerDay'] > 20) {
            $recommendations[] = [
                'type' => 'info',
                'state' => ContextualFeedbackSeverity::INFO->value,
                'title' => 'recommendation.timing.title',
                'message' => 'recommendation.timing.message',
            ];
        }

        return $recommendations;
    }

    /**
     * Get language service.
     */
    private function getLanguageService(): LanguageService
    {
        $languageService = $GLOBALS['LANG'];
        \assert($languageService instanceof LanguageService);
        return $languageService;
    }
}
