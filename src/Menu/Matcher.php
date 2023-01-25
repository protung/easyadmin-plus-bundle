<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Menu;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\MenuItemDto;

interface Matcher
{
    /**
     * @param non-empty-string $currentController
     */
    public function isSelected(MenuItemDto $menuItemDto, AdminContext $adminContext, string $currentController): bool;
}
