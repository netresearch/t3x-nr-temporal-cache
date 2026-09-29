<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Functional\Controller\Backend;

use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\Route;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request as ExtbaseRequest;

/**
 * Builds the backend request the module's actions render with in a functional
 * test: backend application type, normalized params (the JavaScript renderer
 * resolves asset paths through them on 13+) and the Extbase parameters the
 * flash-message ViewHelper reads.
 */
trait BackendModuleRequestTrait
{
    private function createModuleRequest(string $actionName): ExtbaseRequest
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
        $extbaseRequestParameters->setControllerActionName($actionName);
        $extbaseRequestParameters->setPluginName('TemporalCache');

        return new ExtbaseRequest($serverRequest->withAttribute('extbase', $extbaseRequestParameters));
    }
}
