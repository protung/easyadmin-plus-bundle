<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Override;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Report;

/**
 * A CRUD controller that no dashboard has a route for.
 *
 * @template-extends AbstractCrudController<Report>
 */
final class UnroutedReportCrudController extends AbstractCrudController
{
    #[Override]
    public static function getEntityFqcn(): string
    {
        return Report::class;
    }
}
