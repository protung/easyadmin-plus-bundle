<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use Override;
use Protung\EasyAdminPlusBundle\Filter\EnumFilter;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Task;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Enum\TaskStatus;

/**
 * Tasks filtered by their status, which is an enum.
 *
 * @template-extends AbstractCrudController<Task>
 */
final class TaskCrudController extends AbstractCrudController
{
    #[Override]
    public static function getEntityFqcn(): string
    {
        return Task::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setDefaultSort(['id' => 'ASC']);
    }

    /** @return iterable<IdField> */
    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(EnumFilter::new('status', TaskStatus::class));
    }
}
