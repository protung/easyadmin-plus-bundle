<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Override;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Report;

/**
 * A CRUD controller whose detail page needs a role the test user doesn't have.
 *
 * @template-extends AbstractCrudController<Report>
 */
final class DetailForbiddenReportCrudController extends AbstractCrudController
{
    #[Override]
    public static function getEntityFqcn(): string
    {
        return Report::class;
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions->setPermission(Action::DETAIL, 'ROLE_SUPER_ADMIN');
    }
}
