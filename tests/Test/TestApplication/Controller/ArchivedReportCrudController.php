<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Override;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Report;

/**
 * A second CRUD controller, for the URLs of a CRUD controller other than the one under test.
 *
 * @template-extends AbstractCrudController<Report>
 */
final class ArchivedReportCrudController extends AbstractCrudController
{
    #[Override]
    public static function getEntityFqcn(): string
    {
        return Report::class;
    }
}
