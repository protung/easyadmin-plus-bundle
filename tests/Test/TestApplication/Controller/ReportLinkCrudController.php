<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Override;
use Protung\EasyAdminPlusBundle\Field\EntityField;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Report;

/**
 * Entity fields that link each report to itself, through a CRUD controller that only the main dashboard has routes for,
 * through one that no dashboard has routes for and through one whose detail page the user may not open.
 *
 * @template-extends AbstractCrudController<Report>
 */
final class ReportLinkCrudController extends AbstractCrudController
{
    #[Override]
    public static function getEntityFqcn(): string
    {
        return Report::class;
    }

    /** @return iterable<EntityField> */
    #[Override]
    public function configureFields(string $pageName): iterable
    {
        // the ID is the ID of the linked report, so the fields need no association
        yield EntityField::new('id', 'Report')
            ->setCrudController(ReportCrudController::class)
            ->setEntityDisplayField('id');

        yield EntityField::new('id', 'Unrouted report')
            ->setCrudController(UnroutedReportCrudController::class)
            ->setEntityDisplayField('id');

        yield EntityField::new('id', 'Detail forbidden report')
            ->setCrudController(DetailForbiddenReportCrudController::class)
            ->setEntityDisplayField('id');

        // as a to-many association the reports whose ID is the ID of this one, so the report itself
        yield EntityField::new('id', 'Reports')
            ->setCrudController(ReportCrudController::class)
            ->setAssociationToMany()
            ->renderAsList()
            ->setEntityDisplayField('id');

        yield EntityField::new('id', 'Detail forbidden reports')
            ->setCrudController(DetailForbiddenReportCrudController::class)
            ->setAssociationToMany()
            ->renderAsList()
            ->setEntityDisplayField('id');
    }
}
