<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionGroupDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Psl\Dict;
use Psl\Iter;
use Psl\Type;
use Psl\Vec;
use RuntimeException;

/**
 * @template TEntity of object
 * @extends AbstractCrudController<TEntity>
 */
abstract class BaseCrudController extends AbstractCrudController
{
    use FlashMessages;

    /**
     * Calling this method will disable all standard actions.
     */
    public function disableAllActions(Actions $actions): Actions
    {
        return $this->allowOnlyActions($actions);
    }

    /**
     * Calling this method will only disable the standard actions.
     */
    protected function allowOnlyActions(Actions $actions, string ...$allowedActions): Actions
    {
        $allActions = [
            Action::BATCH_DELETE,
            Action::DELETE,
            Action::DETAIL,
            Action::EDIT,
            Action::INDEX,
            Action::NEW,
        ];

        return $actions->disable(
            ...Vec\values(Dict\diff($allActions, $allowedActions)),
        );
    }

    /**
     * Calling this method will only disable the standard actions.
     */
    protected function allowOnlyIndexAction(Actions $actions): Actions
    {
        return $this->allowOnlyActions($actions, Action::INDEX);
    }

    /**
     * Calling this method will only disable the standard actions.
     */
    protected function allowOnlyDetailAction(Actions $actions): Actions
    {
        return $this->allowOnlyActions($actions, Action::DETAIL);
    }

    protected function setActionsPermissions(Actions $actions, string $permission): Actions
    {
        return $this->applyToAllActions(
            $actions,
            static function (ActionDto|ActionGroupDto $actionDto) use ($actions, $permission): void {
                $actions->setPermission($actionDto->getName(), $permission);
            },
        );
    }

    /**
     * @param callable(ActionDto|ActionGroupDto):void $apply
     */
    final protected function applyToAllActions(Actions $actions, callable $apply): Actions
    {
        foreach (Type\mixed_dict()->coerce($actions->getAsDto(null)->getActions()) as $pageActions) {
            Iter\apply($pageActions, $apply);
        }

        return $actions;
    }

    /**
     * @return AdminContext<TEntity>
     */
    protected function currentAdminContext(): AdminContext
    {
        $currentAdminContext = $this->getContext();
        if ($currentAdminContext === null) {
            throw new RuntimeException('Current request is not in an EasyAdmin context.');
        }

        return $currentAdminContext;
    }

    /**
     * Flash messages given as strings are translated in the translation domain of the EasyAdmin context.
     */
    protected function flashMessageTranslationDomain(): string
    {
        return $this->currentAdminContext()->getI18n()->getTranslationDomain();
    }

    protected function adminUrlGenerator(): AdminUrlGeneratorInterface
    {
        return $this->container->get(AdminUrlGeneratorInterface::class);
    }
}
