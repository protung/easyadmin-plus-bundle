<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Override;
use Protung\EasyAdminPlusBundle\Field\EntityField;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Report;

/**
 * Fields whose choices are grouped.
 *
 * @template-extends AbstractCrudController<Report>
 */
final class GroupedReportLinkCrudController extends AbstractCrudController
{
    #[Override]
    public static function getEntityFqcn(): string
    {
        return Report::class;
    }

    /** @return iterable<AssociationField|EntityField> */
    #[Override]
    public function configureFields(string $pageName): iterable
    {
        $groupBy = static fn (Report $report): string => 'Group ' . $report->id;

        yield EntityField::new('displayField')
            ->setCrudController(ReportCrudController::class)
            ->setEntityDisplayField(static fn (Report $report): string => 'Display field ' . $report->id)
            ->setFormTypeOption('group_by', $groupBy);

        yield EntityField::new('callback')
            ->setCrudController(ReportCrudController::class)
            ->autocomplete(callback: static fn (Report $report): string => 'Callback ' . $report->id)
            ->setFormTypeOption('group_by', $groupBy);

        yield AssociationField::new('association')
            ->setCrudController(ReportCrudController::class)
            ->autocomplete()
            ->setFormTypeOption('group_by', 'id');
    }
}
