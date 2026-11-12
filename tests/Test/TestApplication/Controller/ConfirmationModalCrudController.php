<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Override;
use Protung\EasyAdminPlusBundle\Action\ConfirmationModal;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Report;

use function Symfony\Component\Translation\t;

/**
 * The confirmations of the new page's actions, in the ways a {@see ConfirmationModal} can be asked for.
 * Its closures need EasyAdmin 5.10.
 *
 * @template-extends AbstractCrudController<Report>
 */
final class ConfirmationModalCrudController extends AbstractCrudController
{
    #[Override]
    public static function getEntityFqcn(): string
    {
        return Report::class;
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(
                Crud::PAGE_NEW,
                Action::new('closure_modal')
                    ->linkToUrl('#')
                    ->askConfirmation(static fn (): ConfirmationModal => new ConfirmationModal(t('closure_modal.title'), t('closure_modal.content'))),
            )
            ->add(
                Crud::PAGE_NEW,
                Action::new('closure_false')
                    ->linkToUrl('#')
                    ->askConfirmation(static fn (): bool => false),
            )
            ->add(
                Crud::PAGE_NEW,
                Action::new('direct_modal')
                    ->linkToUrl('#')
                    ->askConfirmation(new ConfirmationModal(t('direct_modal.title'), t('direct_modal.content'))),
            );
    }
}
