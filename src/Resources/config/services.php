<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemMatcherInterface;
use EasyCorp\Bundle\EasyAdminBundle\DependencyInjection\EasyAdminExtension;
use EasyCorp\Bundle\EasyAdminBundle\Menu\MenuItemMatcher;
use Symfony\Component\Form\FormTypeInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()->private()->autowire()
        ->instanceof(FieldConfiguratorInterface::class)->tag(EasyAdminExtension::TAG_FIELD_CONFIGURATOR)
        ->instanceof(FormTypeInterface::class)->tag('form.type')
        ->alias(MenuItemMatcherInterface::class, MenuItemMatcher::class);

    $services
        ->load('Protung\\EasyAdminPlusBundle\\', '../../../src/*')
        ->exclude('../../../src/Resources/**/*');
};
