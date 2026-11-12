<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Action;

use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionGroupDto;
use Psl\Type;

final class ActionsWalker
{
    /**
     * Calls `$apply` with every page's actions. An action group is passed as a whole, without its actions.
     *
     * @param callable(ActionDto|ActionGroupDto):void $apply
     */
    public static function applyToAllActions(Actions $actions, callable $apply): Actions
    {
        $actionsByPage = $actions->getAsDto(null)->getActions();

        // Passing `null` asks for every page's actions, which is the array keyed by page name instead of a collection.
        Type\mixed_dict()->assert($actionsByPage);

        foreach ($actionsByPage as $pageActions) {
            foreach ($pageActions as $action) {
                $apply($action);
            }
        }

        return $actions;
    }
}
