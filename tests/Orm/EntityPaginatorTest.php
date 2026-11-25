<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Orm;

use Doctrine\ORM\Tools\SchemaTool;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Protung\EasyAdminPlusBundle\Orm\EntityPaginator;
use Protung\EasyAdminPlusBundle\Test\Controller\AutocompleteActionTestCase;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller\FieldForbiddenReportLinkCrudController;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller\ReportCrudController;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Report;

use function version_compare;

/**
 * The autocomplete of entity fields only uses the display field of the fields the user may see.
 *
 * @template-extends AutocompleteActionTestCase<Report, ReportCrudController>
 */
#[CoversClass(EntityPaginator::class)]
final class EntityPaginatorTest extends AutocompleteActionTestCase
{
    private string $propertyName = 'visible';

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
        return ReportCrudController::class;
    }

    #[Override]
    protected function autocompleteContextCrudControllerFqcn(): string
    {
        return FieldForbiddenReportLinkCrudController::class;
    }

    #[Override]
    protected function autocompleteContextPropertyName(): string
    {
        return $this->propertyName;
    }

    /** @return iterable<string, array{string}> */
    public static function dataProviderTestAutocomplete(): iterable
    {
        yield 'field-visible' => ['visible'];
        yield 'field-forbidden' => ['forbidden'];
        yield 'field-forbidden-with-visible-replacement' => ['replaced'];
        yield 'field-unknown' => ['unknown'];
    }

    #[DataProvider('dataProviderTestAutocomplete')]
    public function testAutocomplete(string $propertyName): void
    {
        $this->propertyName = $propertyName;

        $this->assertPage();
    }

    public function testAutocompleteInForbiddenFieldset(): void
    {
        if (version_compare(EasyAdminBundle::VERSION, '5.0.0', '>=') && version_compare(EasyAdminBundle::VERSION, '5.10.4', '<')) {
            self::markTestSkipped('The permission of a fieldset only applies to its fields since EasyAdmin 4.29.18 and 5.10.4.');
        }

        $this->propertyName = 'inForbiddenFieldset';

        $this->assertPage();
    }
}
