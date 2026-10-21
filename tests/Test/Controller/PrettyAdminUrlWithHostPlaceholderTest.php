<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Protung\EasyAdminPlusBundle\Test\Controller\AdminControllerWebTestCase;
use Protung\EasyAdminPlusBundle\Test\Controller\AdminWebTestCase;
use Protung\EasyAdminPlusBundle\Test\Controller\CustomActionTestCase;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller\ReportCrudController;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Report;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\TestKernel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @template-extends CustomActionTestCase<Report, ReportCrudController>
 */
#[CoversClass(AdminWebTestCase::class)]
#[CoversClass(AdminControllerWebTestCase::class)]
final class PrettyAdminUrlWithHostPlaceholderTest extends CustomActionTestCase
{
    /**
     * @param array<mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        $options['environment'] ??= TestKernel::ENVIRONMENT_TENANT;

        return parent::createKernel($options);
    }

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

    /**
     * @return array<array-key, mixed>
     */
    #[Override]
    protected function prepareAdminUrlRouteParameters(): array
    {
        $routeParameters = parent::prepareAdminUrlRouteParameters();

        $routeParameters['tenant'] = 'www';

        return $routeParameters;
    }

    public function testParameterNamedLikeAHostPlaceholderFillsTheHost(): void
    {
        self::assertSame('//www.localhost/admin/report/1/download/all', $this->prepareAdminUrl(['kind' => 'all']));
        self::assertSame(
            '//acme.localhost/admin/report/1/download/all',
            $this->prepareAdminUrl(['tenant' => 'acme', 'kind' => 'all']),
        );
    }

    public function testRedirectAssertionsAcceptARedirectToAnotherHost(): void
    {
        $this->assertRequestGet(
            [EA::CRUD_ACTION => 'mainTenantLatest', 'tenant' => 'acme', 'kind' => 'all'],
            Response::HTTP_FOUND,
        );

        $this->assertResponseRedirectsToCrudController(
            ReportCrudController::class,
            'download',
            ReportCrudController::LATEST_REPORT_ID,
            ['tenant' => ReportCrudController::MAIN_TENANT, 'kind' => 'all'],
        );
    }
}
