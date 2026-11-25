<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\Controller;

use Doctrine\ORM\Tools\SchemaTool;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Protung\EasyAdminPlusBundle\Test\Controller\AutocompleteActionTestCase;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller\ReportCrudController;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller\RestrictedReportLinkCrudController;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Report;

/**
 * The autocomplete of a field on an edit page that the user may only open for a report.
 *
 * @template-extends AutocompleteActionTestCase<Report, ReportCrudController>
 */
#[CoversClass(AutocompleteActionTestCase::class)]
final class AutocompleteContextEntityTest extends AutocompleteActionTestCase
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
        return ReportCrudController::class;
    }

    #[Override]
    protected function autocompleteContextCrudControllerFqcn(): string
    {
        return RestrictedReportLinkCrudController::class;
    }

    #[Override]
    protected function autocompleteContextPropertyName(): string
    {
        return 'id';
    }

    #[Override]
    protected function autocompleteContextOriginatingPage(): string
    {
        return Crud::PAGE_EDIT;
    }

    #[Override]
    protected function autocompleteContextEntityId(): int
    {
        return 1;
    }

    public function testAutocomplete(): void
    {
        $this->assertPage();
    }
}
