<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Protung\EasyAdminPlusBundle\Test\Controller\AdminControllerWebTestCase;
use Protung\EasyAdminPlusBundle\Test\Controller\CustomActionTestCase;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller\ReportCrudController;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Report;

/**
 * @template-extends CustomActionTestCase<Report, ReportCrudController>
 */
#[CoversClass(AdminControllerWebTestCase::class)]
final class LegacyAdminUrlTest extends CustomActionTestCase
{
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

    public function testAllParametersStayInTheQueryString(): void
    {
        self::assertSame(
            '/admin?crudAction=download'
            . '&crudControllerFqcn=Protung%5CEasyAdminPlusBundle%5CTests%5CTest%5CTestApplication%5CController%5CReportCrudController'
            . '&entityId=2&format=pdf&kind=all',
            $this->prepareAdminUrl(['kind' => 'all', 'format' => 'pdf', EA::ENTITY_ID => '2']),
        );
    }
}
