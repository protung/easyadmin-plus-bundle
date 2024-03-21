<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemMatcherInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Orm\EntityPaginatorInterface;
use EasyCorp\Bundle\EasyAdminBundle\DependencyInjection\EasyAdminExtension;
use EasyCorp\Bundle\EasyAdminBundle\Menu\MenuItemMatcher;
use EasyCorp\Bundle\EasyAdminBundle\Orm\EntityRepository as EasyAdminEntityRepository;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Protung\EasyAdminPlusBundle\Filter\Configurator\EntityConfigurator;
use Protung\EasyAdminPlusBundle\Orm\EntityPaginator;
use Protung\EasyAdminPlusBundle\Orm\EntityRepository;
use Symfony\Component\Form\FormTypeInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()->private()->autowire()->autoconfigure()
        ->instanceof(FieldConfiguratorInterface::class)->tag(EasyAdminExtension::TAG_FIELD_CONFIGURATOR)
        ->instanceof(FormTypeInterface::class)->tag('form.type')
        ->alias(MenuItemMatcherInterface::class, MenuItemMatcher::class);

    $services
        ->load('Protung\\EasyAdminPlusBundle\\', '../../../src/*')
        ->exclude('../../../src/Resources/**/*');

    $services->set(EntityRepository::class)
        ->decorate(EasyAdminEntityRepository::class)
        ->autoconfigure()
        ->autowire();

    $services->set(EntityConfigurator::class)
        ->autowire()
        ->autoconfigure()
        ->private()
        ->arg('$adminUrlGenerator', service(AdminUrlGenerator::class))
        ->tag(EasyAdminExtension::TAG_FILTER_CONFIGURATOR, ['priority' => -1]); // must be after \EasyCorp\Bundle\EasyAdminBundle\Filter\Configurator\EntityConfigurator

    $services->set(EntityPaginator::class)
        ->decorate(EntityPaginatorInterface::class)
        ->arg('$adminUrlGenerator', service(AdminUrlGenerator::class))
        ->autowire()
        ->autoconfigure()
        ->private();
};
