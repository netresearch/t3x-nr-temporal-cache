<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Functional\Controller\Backend;

use Netresearch\TemporalCache\Controller\Backend\TemporalCacheController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Routing\Route;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request as ExtbaseRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The wizard's analysis step renders each recommendation as a core callout whose
 * severity the controller sets. The harmonization recommendation only appears
 * while harmonization is disabled, which is why this runs in its own class: the
 * extension configuration is fixed per test class.
 */
#[CoversClass(TemporalCacheController::class)]
final class TemporalCacheWizardRecommendationTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler', 'reports'];

    protected array $testExtensionsToLoad = [
        'nr_temporal_cache',
    ];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'nr_temporal_cache' => [
                'scoping' => [
                    'strategy' => 'per-content',
                ],
                'timing' => [
                    'strategy' => 'dynamic',
                ],
                'harmonization' => [
                    'enabled' => false,
                ],
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/be_users.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/pages.csv');
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($GLOBALS['BE_USER']);
    }

    #[Test]
    public function analysisRendersHarmonizationWarningAndTimingInfo(): void
    {
        // One transition on each of the next 25 days: more than 10 transition days
        // with harmonization off recommends harmonization (warning), more than 20
        // with dynamic timing recommends scheduler timing (info).
        $connection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        $now = \time();
        for ($day = 1; $day <= 25; $day++) {
            $connection->insert('tt_content', [
                'uid' => 9000 + $day,
                'pid' => 1,
                'header' => 'Daily ' . $day,
                'CType' => 'text',
                'starttime' => $now + $day * 86400,
            ]);
        }

        $request = $this->createRequest();
        $GLOBALS['TYPO3_REQUEST'] = $request;
        $response = $this->get(TemporalCacheController::class)->wizardAction($request, 'analysis');
        self::assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();

        self::assertMatchesRegularExpression(
            '#<div class="callout callout-warning">(?:(?!<div class="callout ).)*Enable Harmonization#s',
            $body
        );
        self::assertMatchesRegularExpression(
            '#<div class="callout callout-info">(?:(?!<div class="callout ).)*Use Scheduler Timing#s',
            $body
        );
    }

    private function createRequest(): ExtbaseRequest
    {
        $serverRequest = new ServerRequest('http://localhost', 'GET');
        $route = new Route('/module/temporal-cache', []);
        $route->setOption('packageName', 'nr_temporal_cache');

        $serverRequest = $serverRequest
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('normalizedParams', NormalizedParams::createFromRequest($serverRequest))
            ->withAttribute('route', $route)
            ->withAttribute('module', null)
            ->withAttribute('moduleData', null);

        $extbaseRequestParameters = new ExtbaseRequestParameters();
        $extbaseRequestParameters->setControllerExtensionName('NrTemporalCache');
        $extbaseRequestParameters->setControllerName('TemporalCache');
        $extbaseRequestParameters->setControllerActionName('wizard');
        $extbaseRequestParameters->setPluginName('TemporalCache');

        return new ExtbaseRequest($serverRequest->withAttribute('extbase', $extbaseRequestParameters));
    }
}
