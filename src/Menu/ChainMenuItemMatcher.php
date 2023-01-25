<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Menu;

use EasyCorp\Bundle\EasyAdminBundle\Dto\MenuItemDto;
use EasyCorp\Bundle\EasyAdminBundle\Menu\MenuItemMatcher as EasyAdminMenuItemMatcher;
use EasyCorp\Bundle\EasyAdminBundle\Menu\MenuItemMatcherInterface;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use Psl\Type;

use function array_values;
use function is_array;

/**
 * Custom menu item matcher to simplify special algorithms to choose what menu item is selected.
 * This should be created as a service and aliased to `EasyCorp\Bundle\EasyAdminBundle\Menu\MenuItemMatcherInterface` service ID.
 * The compiler pass `\Protung\EasyAdminPlusBundle\DependencyInjection\CompilerPass\EasyAdminMenuItemMatcher` should be registered to fix the MenuFactory definition.
 */
final class ChainMenuItemMatcher implements MenuItemMatcherInterface
{
    private readonly EasyAdminMenuItemMatcher $easyAdminMenuItemMatcher;

    private readonly AdminContextProvider $adminContextProvider;

    /** @var non-empty-list<Matcher> */
    private readonly array $matchers;

    public function __construct(
        EasyAdminMenuItemMatcher $easyAdminMenuItemMatcher,
        AdminContextProvider $adminContextProvider,
        Matcher $matcher,
        Matcher ...$matchers,
    ) {
        $this->easyAdminMenuItemMatcher = $easyAdminMenuItemMatcher;
        $this->adminContextProvider     = $adminContextProvider;
        $this->matchers                 = [$matcher, ...array_values($matchers)];
    }

    public function isSelected(MenuItemDto $menuItemDto): bool
    {
        $adminContext = $this->adminContextProvider->getContext();
        if ($adminContext === null || $menuItemDto->isMenuSection()) {
            return $this->easyAdminMenuItemMatcher->isSelected($menuItemDto);
        }

        $currentController = $adminContext->getRequest()->attributes->get('_controller');
        if ($currentController === null) {
            return $this->easyAdminMenuItemMatcher->isSelected($menuItemDto);
        }

        $currentController = Type\union(
            Type\non_empty_string(), // class
            Type\shape([0 => Type\non_empty_string(), 1 => Type\non_empty_string()]), // class + action
        )
            ->coerce($currentController);

        $currentController = is_array($currentController) ? $currentController[0] : $currentController;

        foreach ($this->matchers as $matcher) {
            if ($matcher->isSelected($menuItemDto, $adminContext, $currentController)) {
                return true;
            }
        }

        return $this->easyAdminMenuItemMatcher->isSelected($menuItemDto);
    }

    public function isExpanded(MenuItemDto $menuItemDto): bool
    {
        return $this->easyAdminMenuItemMatcher->isExpanded($menuItemDto);
    }
}
