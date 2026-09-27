<?php

declare(strict_types=1);

namespace Netresearch\TemporalCache\Tests\Unit\Resources;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Reads the backend module templates as source. The functional suite renders them,
 * but `f:link.action` produces no `<a>` without a routed module request, so the
 * classes on the module's links are only visible here.
 *
 * Every class below either does not exist in TYPO3's backend CSS (so the element
 * renders unstyled) or keeps one colour in both the light and the dark scheme.
 */
final class BackendTemplateClassesTest extends UnitTestCase
{
    private const TEMPLATE_DIRECTORY = '/Resources/Private/Templates/Backend/TemporalCache/';

    /**
     * @return array<string, array{string}>
     */
    public static function templateProvider(): array
    {
        return [
            'Dashboard' => ['Dashboard.html'],
            'Content' => ['Content.html'],
            'Wizard' => ['Wizard.html'],
        ];
    }

    #[Test]
    #[DataProvider('templateProvider')]
    public function templateUsesNoClassOutsideCoreThemes(string $template): void
    {
        $source = $this->read($template);

        self::assertDoesNotMatchRegularExpression('/outline-(primary|secondary|success|danger|warning|info)/', $source, 'core has no btn-outline-* classes');
        self::assertDoesNotMatchRegularExpression('/\bbtn-(secondary|light|dark)\b/', $source, 'fixed-colour button');
        self::assertDoesNotMatchRegularExpression('/\bbtn-lg\b/', $source, 'core v14 has no btn-lg');
        self::assertDoesNotMatchRegularExpression('/\bbadge bg-/', $source, 'badges use badge-*');
        self::assertDoesNotMatchRegularExpression('/\balert(-[a-z{}.]+)?\b/', $source, 'info boxes use f:be.infobox');
        self::assertDoesNotMatchRegularExpression('/\b(text-white|bg-success|bg-primary|bg-secondary|bg-info|bg-danger)\b/', $source, 'fixed-colour utility');
        self::assertDoesNotMatchRegularExpression('/\btable-(responsive|light|sm)\b/', $source, 'core table classes only');
    }

    #[Test]
    public function inactiveContentFilterButtonsAreDefaultButtons(): void
    {
        self::assertStringContainsString(
            "class=\"btn btn-{f:if(condition: '{filter} == {value}', then: 'primary', else: 'default')}\"",
            $this->read('Content.html')
        );
    }

    #[Test]
    public function dashboardWizardTileIsDefaultButton(): void
    {
        self::assertStringContainsString(
            '<f:link.action action="wizard" class="btn btn-default">',
            $this->read('Dashboard.html')
        );
    }

    #[Test]
    public function wizardSecondaryActionsAreDefaultButtons(): void
    {
        self::assertSame(4, \substr_count($this->read('Wizard.html'), 'class="btn btn-default"'));
    }

    #[Test]
    public function rowCheckboxesHaveAnAccessibleName(): void
    {
        // The rows only get a checkbox when harmonization would change them, which
        // the functional fixtures do not produce, so the markup is pinned here.
        self::assertMatchesRegularExpression(
            '/class="form-check-input content-checkbox"\s[^>]*aria-label="\{item\.content\.title\}/',
            $this->read('Content.html')
        );
    }

    #[Test]
    public function tableCheckboxesSitInCoreFormCheckWrapper(): void
    {
        // Core sizes .form-check-input only inside .form-check; bare, the inputs
        // render 0x0 on 13.4 and 14.3. Same wrapper as core's record list.
        $source = $this->read('Content.html');

        self::assertMatchesRegularExpression(
            '#<span class="form-check form-check-type-toggle">\s*<input type="checkbox" id="select-all" class="form-check-input"#',
            $source
        );
        self::assertMatchesRegularExpression(
            '#<span class="form-check form-check-type-toggle">\s*<input\s+type="checkbox"\s+class="form-check-input content-checkbox"#',
            $source
        );
    }

    #[Test]
    #[DataProvider('templateProvider')]
    public function infoboxStatesAreIntegerLiterals(string $template): void
    {
        // InfoboxViewHelper::STATE_* is deprecated in 14; the ContextualFeedbackSeverity
        // enum is rejected by 12 and 13. Integer literals work on all three.
        $source = $this->read($template);

        self::assertStringNotContainsString('InfoboxViewHelper::STATE_', $source);
        self::assertDoesNotMatchRegularExpression('/<f:be\.infobox\b(?![^>]*\bstate="(-1|0|1|2|-2|\{recommendation\.state\})")[^>]*>/', $source, 'every infobox has an explicit state');
    }

    #[Test]
    public function extensionShipsNoModuleLayoutOfItsOwn(): void
    {
        self::assertFileDoesNotExist(
            \dirname(__DIR__, 3) . '/Resources/Private/Layouts/Module.html',
            "an extension Module layout replaces core's and drops the doc header"
        );
    }

    private function read(string $template): string
    {
        $source = \file_get_contents(\dirname(__DIR__, 3) . self::TEMPLATE_DIRECTORY . $template);
        self::assertIsString($source);

        return $source;
    }
}
