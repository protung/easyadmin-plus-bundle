<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\DependencyInjection\CompilerPass;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemMatcherInterface;
use EasyCorp\Bundle\EasyAdminBundle\Factory\MenuFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @see https://github.com/EasyCorp/EasyAdminBundle/pull/5620
 *
 * @codeCoverageIgnore
 */
final class EasyAdminMenuItemMatcher implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $serviceDefinition = $container->getDefinition(MenuFactory::class);
        $serviceDefinition->setArgument(4, new Reference(MenuItemMatcherInterface::class));
    }
}
