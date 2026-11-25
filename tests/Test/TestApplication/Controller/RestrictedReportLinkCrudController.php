<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Override;
use Protung\EasyAdminPlusBundle\Field\EntityField;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Report;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Security\ReportVoter;

/**
 * An entity field on an edit page that the user may only open for a report.
 *
 * @template-extends AbstractCrudController<Report>
 */
final class RestrictedReportLinkCrudController extends AbstractCrudController
{
    #[Override]
    public static function getEntityFqcn(): string
    {
        return Report::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityPermission(ReportVoter::ACCESS);
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions->setPermission(Action::EDIT, ReportVoter::ACCESS);
    }

    /** @return iterable<EntityField> */
    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield EntityField::new('id', 'Report')
            ->setCrudController(ReportCrudController::class)
            ->setEntityDisplayField(static fn (Report $report): string => 'Report number ' . $report->id);
    }
}
