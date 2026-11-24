<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use Override;

/**
 * Notes whose edit form the user may open but not save.
 */
final class SaveForbiddenNoteCrudController extends SwitchNoteCrudController
{
    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions->setPermissions([
            Action::SAVE_AND_RETURN => 'ROLE_NOBODY',
            Action::SAVE_AND_CONTINUE => 'ROLE_NOBODY',
        ]);
    }
}
