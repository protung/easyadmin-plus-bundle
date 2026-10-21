<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Protung\EasyAdminPlusBundle\Test\Controller\AdminControllerWebTestCase;
use Protung\EasyAdminPlusBundle\Test\Controller\AdminWebTestCase;
use Protung\EasyAdminPlusBundle\Test\Controller\CustomActionTestCase;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller\ArchivedReportCrudController;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller\ReportCrudController;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Report;
use Symfony\Component\HttpFoundation\Response;

/**
 * @template-extends CustomActionTestCase<Report, ReportCrudController>
 */
#[CoversClass(AdminWebTestCase::class)]
#[CoversClass(AdminControllerWebTestCase::class)]
final class PrettyAdminUrlTest extends CustomActionTestCase
{
    #[Override]
    protected static function usePrettyUrls(): bool
    {
        return true;
    }

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

    public function testParameterNamedLikeARoutePlaceholderFillsThePath(): void
    {
        self::assertSame('/admin/report/1/download/all', $this->prepareAdminUrl(['kind' => 'all']));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function provideQueryParameters(): iterable
    {
        yield 'other parameters' => [
            ['sort' => 'desc', 'kind' => 'all', 'format' => 'pdf'],
            '/admin/report/1/download/all?format=pdf&sort=desc',
        ];

        yield 'placeholder of another route' => [
            [EA::CRUD_ACTION => 'latest', 'kind' => 'all', EA::ENTITY_ID => '2'],
            '/admin/report/latest/all?entityId=2',
        ];
    }

    /**
     * @param array<array-key, mixed> $urlParameters
     */
    #[DataProvider('provideQueryParameters')]
    public function testParametersNotNamedLikeARoutePlaceholderStayInTheSortedQueryString(
        array $urlParameters,
        string $expectedUrl,
    ): void {
        self::assertSame($expectedUrl, $this->prepareAdminUrl($urlParameters));
    }

    public function testExplicitPlaceholderTakesPrecedenceOverThePreparedRouteParameter(): void
    {
        self::assertSame(
            '/admin/report/2/download/all',
            $this->prepareAdminUrl([EA::ENTITY_ID => '2', 'kind' => 'all']),
        );
    }

    public function testCrudControllerAndActionSelectTheRoute(): void
    {
        self::assertSame(
            '/admin/archived-report',
            $this->prepareAdminUrl([EA::CRUD_CONTROLLER_FQCN => ArchivedReportCrudController::class, EA::CRUD_ACTION => Action::INDEX]),
        );
    }

    public function testRequestAndRedirectAssertionsFillRoutePlaceholders(): void
    {
        $this->assertRequestGet([EA::CRUD_ACTION => 'latest', 'kind' => 'all', 'format' => 'pdf'], Response::HTTP_FOUND);

        $this->assertResponseRedirectsToCrudController(
            ReportCrudController::class,
            'download',
            ReportCrudController::LATEST_REPORT_ID,
            ['kind' => 'all', 'format' => 'pdf'],
        );
        $this->assertResponseIsRedirect([
            EA::CRUD_ACTION => 'download',
            EA::ENTITY_ID => ReportCrudController::LATEST_REPORT_ID,
            'kind' => 'all',
            'format' => 'pdf',
        ]);
    }

    public function testEntityIdGivenToTheRedirectAssertionTakesPrecedenceOverTheRedirectParameters(): void
    {
        $this->assertRequestGet([EA::CRUD_ACTION => 'latest', 'kind' => 'all'], Response::HTTP_FOUND);

        $this->assertResponseRedirectsToCrudController(
            ReportCrudController::class,
            'download',
            ReportCrudController::LATEST_REPORT_ID,
            [EA::ENTITY_ID => '2', 'kind' => 'all'],
        );
    }
}
