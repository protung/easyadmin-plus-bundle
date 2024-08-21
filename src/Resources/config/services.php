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
use Protung\EasyAdminPlusBundle\Field\Configurator\CallbackConfigurableConfigurator;
use Protung\EasyAdminPlusBundle\Field\Configurator\CallbackConfigurableConfiguratorAfterCommonPostConfigurator;
use Protung\EasyAdminPlusBundle\Field\Configurator\CallbackConfigurableConfiguratorBeforeCommonPreConfigurator;
use Protung\EasyAdminPlusBundle\Filter\Configurator\EntityConfigurator;
use Protung\EasyAdminPlusBundle\Orm\EntityPaginator;
use Protung\EasyAdminPlusBundle\Orm\EntityRepository;
use Protung\EasyAdminPlusBundle\Router\AutocompleteActionAdminUrlGenerator;
use Symfony\Component\Form\FormTypeInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()->private()->autowire()->autoconfigure()
        ->instanceof(FieldConfiguratorInterface::class)->tag(EasyAdminExtension::TAG_FIELD_CONFIGURATOR)
        ->instanceof(FormTypeInterface::class)->tag('form.type')
        ->alias(MenuItemMatcherInterface::class, MenuItemMatcher::class);

    $services
        ->load('Protung\\EasyAdminPlusBundle\\', '../../../src/*')
        ->exclude(['../../../src/Resources/**/*', '../../../src/Test/**/*']);

    $services->set(EntityRepository::class)
        ->decorate(EasyAdminEntityRepository::class)
        ->autoconfigure()
        ->autowire();

    $services->set(EntityConfigurator::class)
        ->autowire()
        ->autoconfigure()
        ->private()
        ->tag(EasyAdminExtension::TAG_FILTER_CONFIGURATOR, ['priority' => -1]); // must be after \EasyCorp\Bundle\EasyAdminBundle\Filter\Configurator\EntityConfigurator

    $services->set(EntityPaginator::class)
        ->decorate(EntityPaginatorInterface::class)
        ->arg('$adminUrlGenerator', service(AdminUrlGenerator::class))
        ->autowire()
        ->autoconfigure()
        ->private();

    $services->set(AutocompleteActionAdminUrlGenerator::class)
        ->arg('$adminUrlGenerator', service(AdminUrlGenerator::class))
        ->autowire()
        ->autoconfigure()
        ->private();

    $services->set(CallbackConfigurableConfiguratorBeforeCommonPreConfigurator::class)
        ->autowire()
        ->autoconfigure()
        ->private()
        ->tag(EasyAdminExtension::TAG_FIELD_CONFIGURATOR, ['priority' => 10_000]); // must be before \EasyCorp\Bundle\EasyAdminBundle\Field\Configurator\CommonPostConfigurator

    $services->set(CallbackConfigurableConfigurator::class)
        ->autowire()
        ->autoconfigure()
        ->private()
        ->tag(EasyAdminExtension::TAG_FIELD_CONFIGURATOR);

    $services->set(CallbackConfigurableConfiguratorAfterCommonPostConfigurator::class)
        ->autowire()
        ->autoconfigure()
        ->private()
        ->tag(EasyAdminExtension::TAG_FIELD_CONFIGURATOR, ['priority' => -10_000]); // must be after \EasyCorp\Bundle\EasyAdminBundle\Field\Configurator\CommonPreConfigurator
};
