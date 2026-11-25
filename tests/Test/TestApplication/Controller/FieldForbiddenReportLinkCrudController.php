<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use Override;
use Protung\EasyAdminPlusBundle\Field\EntityField;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Report;

/**
 * Entity fields the user may see and may not see, on their own or because of the fieldset they are in.
 *
 * @template-extends AbstractCrudController<Report>
 */
final class FieldForbiddenReportLinkCrudController extends AbstractCrudController
{
    #[Override]
    public static function getEntityFqcn(): string
    {
        return Report::class;
    }

    /** @return iterable<EntityField|FormField> */
    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield self::reportField('visible', 'Visible');
        yield self::reportField('forbidden', 'Forbidden')->setPermission('ROLE_NOBODY');
        yield self::reportField('replaced', 'Forbidden')->setPermission('ROLE_NOBODY');
        yield self::reportField('replaced', 'Replacement');

        yield FormField::addFieldset('Forbidden fieldset')->setPermission('ROLE_NOBODY');
        yield self::reportField('inForbiddenFieldset', 'Forbidden');
    }

    private static function reportField(string $propertyName, string $label): EntityField
    {
        return EntityField::new($propertyName)
            ->setCrudController(ReportCrudController::class)
            ->setEntityDisplayField(static fn (Report $report): string => $label . ' ' . $report->id);
    }
}
