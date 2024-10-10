<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Menu;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemMatcherInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\MenuItemDto;
use EasyCorp\Bundle\EasyAdminBundle\Menu\MenuItemMatcher as EasyAdminMenuItemMatcher;
use Psl\Iter;
use Psl\Type;
use Symfony\Component\HttpFoundation\Request;

use function array_values;
use function is_array;

/**
 * Custom menu item matcher to simplify special algorithms to choose what menu item is selected.
 * This should be created as a service and aliased to `EasyCorp\Bundle\EasyAdminBundle\Menu\MenuItemMatcherInterface` service ID.
 */
final readonly class ChainMenuItemMatcher implements MenuItemMatcherInterface
{
    private EasyAdminMenuItemMatcher $easyAdminMenuItemMatcher;

    /** @var non-empty-list<Matcher> */
    private array $matchers;

    public function __construct(
        EasyAdminMenuItemMatcher $easyAdminMenuItemMatcher,
        Matcher $matcher,
        Matcher ...$matchers,
    ) {
        $this->easyAdminMenuItemMatcher = $easyAdminMenuItemMatcher;
        $this->matchers                 = [$matcher, ...array_values($matchers)];
    }

    /**
     * {@inheritDoc}
     */
    public function markSelectedMenuItem(array $menuItems, Request $request): array
    {
        $currentController = $request->attributes->get('_controller');
        if ($currentController === null) {
            return $this->easyAdminMenuItemMatcher->markSelectedMenuItem($menuItems, $request);
        }

        $currentController = Type\union(
            Type\non_empty_string(), // class
            Type\shape([0 => Type\non_empty_string(), 1 => Type\non_empty_string()]), // class + action
        )
            ->coerce($currentController);

        $currentController = is_array($currentController) ? $currentController[0] : $currentController;

        foreach ($this->matchers as $matcher) {
            foreach ($menuItems as $menuItemDto) {
                $subItems = $menuItemDto->getSubItems();
                if ($subItems !== []) {
                    $menuItemDto->setSubItems($this->markSelectedMenuItem($subItems, $request));

                    $hasSubmenuSelected = Iter\any(
                        $subItems,
                        static fn (MenuItemDto $subItemDto): bool => $subItemDto->isSelected(),
                    );
                    $menuItemDto->setSelected($hasSubmenuSelected);
                    continue;
                }

                if ($matcher->shouldBeSelected($menuItemDto, $currentController)) {
                    $menuItemDto->setSelected(true);
                    break;
                }
            }
        }

        return $this->easyAdminMenuItemMatcher->markSelectedMenuItem($menuItems, $request);
    }
}
