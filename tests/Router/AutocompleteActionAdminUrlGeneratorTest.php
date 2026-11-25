<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Router;

use ArrayObject;
use Doctrine\ORM\Tools\SchemaTool;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Context\AdminContextInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\CrudDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Protung\EasyAdminPlusBundle\Field\EntityField;
use Protung\EasyAdminPlusBundle\Orm\EntityPaginator;
use Protung\EasyAdminPlusBundle\Router\AutocompleteActionAdminUrlGenerator;
use Protung\EasyAdminPlusBundle\Test\Controller\CustomActionTestCase;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller\ReportCrudController;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller\RestrictedReportLinkCrudController;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Report;
use Psl\Json;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The autocomplete of an entity field on an edit page whose permission depends on the edited entity.
 *
 * @template-extends CustomActionTestCase<Report, RestrictedReportLinkCrudController>
 */
#[CoversClass(AutocompleteActionAdminUrlGenerator::class)]
#[CoversClass(EntityPaginator::class)]
final class AutocompleteActionAdminUrlGeneratorTest extends CustomActionTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $entityManager = $this->getEntityManager();
        $metadata      = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool    = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $report     = new Report();
        $report->id = 1;
        $entityManager->persist($report);
        $entityManager->flush();
        $entityManager->clear();
    }

    #[Override]
    protected static function usePrettyUrls(): bool
    {
        return true;
    }

    #[Override]
    protected function controllerUnderTest(): string
    {
        return RestrictedReportLinkCrudController::class;
    }

    #[Override]
    protected function actionName(): string
    {
        return Action::EDIT;
    }

    #[Override]
    protected function entityIdUnderTest(): int
    {
        return 1;
    }

    public function testUserMayAutocompleteAFieldOfAPageThatOnlyTheEditedEntityGrantsAccessTo(): void
    {
        $url = $this->assertRequestGet()
            ->filter('[data-ea-autocomplete-endpoint-url]')
            ->attr('data-ea-autocomplete-endpoint-url');
        self::assertIsString($url);

        $this->getClient()->request(Request::METHOD_GET, $url);

        $response = $this->getClient()->getResponse();
        self::assertResponseStatusCode($response, Response::HTTP_OK);
        self::assertSame(
            ['results' => [['entityId' => '1', 'entityAsString' => 'Report number 1']], 'next_page' => null],
            Json\decode((string) $response->getContent()),
        );
    }

    /** @return iterable<string, array{string, Request, Report|null, string|null}> */
    public static function dataProviderTestEntityIdOfTheOriginatingPage(): iterable
    {
        $report     = new Report();
        $report->id = 3;

        yield 'route parameter' => [Crud::PAGE_EDIT, new Request(attributes: [EA::ENTITY_ID => '2']), $report, '2'];
        yield 'route parameter named id' => [Crud::PAGE_EDIT, new Request(attributes: ['id' => '2']), $report, '2'];
        yield 'mapped route parameter' => [Crud::PAGE_EDIT, new Request(attributes: ['_route_params' => [EA::ENTITY_ID => '2']]), $report, '2'];
        yield 'query parameter' => [Crud::PAGE_DETAIL, new Request([EA::ENTITY_ID => '2']), $report, '2'];
        yield 'entity of the context' => [Crud::PAGE_EDIT, new Request(), $report, '3'];
        yield 'not an ID' => [Crud::PAGE_EDIT, new Request(attributes: [EA::ENTITY_ID => $report]), $report, null];
        yield 'no entity' => [Crud::PAGE_EDIT, new Request(), null, null];
        yield 'index page' => [Crud::PAGE_INDEX, new Request([EA::ENTITY_ID => '2']), $report, null];
        yield 'new page' => [Crud::PAGE_NEW, new Request([EA::ENTITY_ID => '2']), $report, null];
    }

    #[DataProvider('dataProviderTestEntityIdOfTheOriginatingPage')]
    public function testEntityIdOfTheOriginatingPage(string $originatingPage, Request $request, Report|null $report, string|null $expectedEntityId): void
    {
        $crudDto = new CrudDto();
        $crudDto->setControllerFqcn(RestrictedReportLinkCrudController::class);

        $context = self::createStub(AdminContextInterface::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getCrud')->willReturn($crudDto);
        $context->method('getEntity')->willReturn(new EntityDto(Report::class, $this->getEntityManager()->getClassMetadata(Report::class), null, $report));

        $autocompleteContexts = new ArrayObject();
        $adminUrlGenerator    = self::createStub(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('set')->willReturnCallback(
            static function (string $name, mixed $value) use ($adminUrlGenerator, $autocompleteContexts): AdminUrlGeneratorInterface {
                if ($name === EntityField::PARAM_AUTOCOMPLETE_CONTEXT) {
                    $autocompleteContexts->append($value);
                }

                return $adminUrlGenerator;
            },
        );
        foreach (['setController', 'setAction', 'setEntityId', 'unset'] as $method) {
            $adminUrlGenerator->method($method)->willReturnSelf();
        }

        new AutocompleteActionAdminUrlGenerator($adminUrlGenerator)->generate($context, ReportCrudController::class, 'id', $originatingPage, true);

        $expectedAutocompleteContext = [
            EA::CRUD_CONTROLLER_FQCN => RestrictedReportLinkCrudController::class,
            'propertyName' => 'id',
            'originatingPage' => $originatingPage,
            EntityField::OPTION_ENTITY_DISPLAY_FIELD => true,
        ];
        if ($expectedEntityId !== null) {
            $expectedAutocompleteContext[EA::ENTITY_ID] = $expectedEntityId;
        }

        self::assertSame([$expectedAutocompleteContext], $autocompleteContexts->getArrayCopy());
    }
}
