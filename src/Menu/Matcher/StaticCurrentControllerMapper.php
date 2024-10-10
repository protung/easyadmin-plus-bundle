<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Menu\Matcher;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Dto\MenuItemDto;
use Protung\EasyAdminPlusBundle\Menu\Matcher;

use function parse_str;
use function parse_url;

use const PHP_URL_QUERY;

/**
 * Select a menu item based of the current controller (FQCN, id, etc.).
 *
 * Example:
 *
 * Given the current page is TicketCrudController::class and the following map
 *      [
 *       'CustomerAddressCrudController::class' => 'CustomerCrudController::class',
 *       'SubscriptionCrudController::class' => 'ProductCrudController::class',
 *       'TicketCrudController::class' => 'ProductCrudController::class',
 *      ]
 * the menu item linked with the ProductCrudController::class (or it's defined entity) will be marked as selected.
 */
final class StaticCurrentControllerMapper implements Matcher
{
    /** @var array<non-empty-string, non-empty-string> */
    private readonly array $controllersMap;

    /**
     * @param array<non-empty-string, non-empty-string> $controllersMap
     */
    public function __construct(array $controllersMap)
    {
        $this->controllersMap = $controllersMap;
    }

    public function shouldBeSelected(MenuItemDto $menuItemDto, string $currentController): bool
    {
        $menuItemQueryString     = $menuItemDto->getLinkUrl() === null ? null : parse_url($menuItemDto->getLinkUrl(), PHP_URL_QUERY);
        $menuItemQueryParameters = [];
        if ($menuItemQueryString !== null) {
            parse_str($menuItemQueryString, $menuItemQueryParameters);
        }

        $controllerInLink = $menuItemQueryParameters[EA::CRUD_CONTROLLER_FQCN] ?? null;

        return $controllerInLink === ($this->controllersMap[$currentController] ?? $currentController);
    }
}
