<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\Controller;

use Generator;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Protung\EasyAdminPlusBundle\Test\Controller\AdminControllerWebTestCase;
use Protung\EasyAdminPlusBundle\Test\Controller\CustomActionTestCase;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller\ReportCrudController;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Report;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @template-extends CustomActionTestCase<Report, ReportCrudController>
 */
#[CoversClass(AdminControllerWebTestCase::class)]
final class CountryExtractionTest extends CustomActionTestCase
{
    private const string FLAG = '<svg xmlns="http://www.w3.org/2000/svg" class="country-flag" height="17" viewBox="0 0 25 17"><title>Japan</title></svg>';

    #[Override]
    protected function controllerUnderTest(): string
    {
        return ReportCrudController::class;
    }

    #[Override]
    protected function actionName(): string
    {
        return 'download';
    }

    #[Override]
    protected function entityIdUnderTest(): int
    {
        return 1;
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function dataProviderTestExtractCountry(): Generator
    {
        yield 'before-easyadmin-5.10' => [
            '<span class="country-flag-wrapper country-flag-with-name">' . self::FLAG . 'Japan</span>',
        ];

        yield 'since-easyadmin-5.10' => [
            '<span class="country-flag-wrapper country-flag-with-name">' . self::FLAG
            . '<span class="country-flag-name" style="margin-inline-start: var(--flag-name-gap, 0.375em)">Japan</span></span>',
        ];
    }

    #[DataProvider('dataProviderTestExtractCountry')]
    public function testExtractCountry(string $flagHtml): void
    {
        $countryValue = new Crawler('<div class="field-value">' . $flagHtml . '</div>');

        self::assertSame(
            ['name' => 'Japan', 'flag' => 'Japan'],
            $this->extractCountry($countryValue->filter('div.field-value')),
        );
    }
}
