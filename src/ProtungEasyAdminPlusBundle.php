<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemMatcherInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Orm\EntityPaginatorInterface;
use EasyCorp\Bundle\EasyAdminBundle\DependencyInjection\EasyAdminExtension;
use EasyCorp\Bundle\EasyAdminBundle\Menu\MenuItemMatcher;
use Override;
use Protung\EasyAdminPlusBundle\Field\Configurator\CallbackConfigurableConfigurator;
use Protung\EasyAdminPlusBundle\Field\Configurator\CallbackConfigurableConfiguratorAfterCommonPostConfigurator;
use Protung\EasyAdminPlusBundle\Field\Configurator\CallbackConfigurableConfiguratorBeforeCommonPreConfigurator;
use Protung\EasyAdminPlusBundle\Filter\Configurator\EntityConfigurator;
use Protung\EasyAdminPlusBundle\Orm\EntityPaginator;
use Protung\EasyAdminPlusBundle\Router\AutocompleteActionAdminUrlGenerator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class ProtungEasyAdminPlusBundle extends AbstractBundle
{
    /**
     * @param array<mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $services = $configurator->services();

        $services->defaults()->private()->autowire()->autoconfigure();
        $services->instanceof(FieldConfiguratorInterface::class)->tag(EasyAdminExtension::TAG_FIELD_CONFIGURATOR);
        $services->instanceof(FormTypeInterface::class)->tag('form.type');
        $services->alias(MenuItemMatcherInterface::class, MenuItemMatcher::class);

        $services
            ->load(__NAMESPACE__ . '\\', __DIR__ . '/')
            ->exclude([__DIR__ . '/Test/', __FILE__]);

        $services->set(EntityConfigurator::class)
            ->autowire()
            ->autoconfigure()
            ->private()
            ->tag(EasyAdminExtension::TAG_FILTER_CONFIGURATOR, ['priority' => -1]); // must be after \EasyCorp\Bundle\EasyAdminBundle\Filter\Configurator\EntityConfigurator

        $services->set(EntityPaginator::class)
            ->decorate(EntityPaginatorInterface::class)
            ->autowire()
            ->autoconfigure()
            ->private();

        $services->set(AutocompleteActionAdminUrlGenerator::class)
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
            ->tag(EasyAdminExtension::TAG_FIELD_CONFIGURATOR, ['priority' => 1]); // by design, we want this to kick in before any of the other field configurators with default priority

        $services->set(CallbackConfigurableConfiguratorAfterCommonPostConfigurator::class)
            ->autowire()
            ->autoconfigure()
            ->private()
            ->tag(EasyAdminExtension::TAG_FIELD_CONFIGURATOR, ['priority' => -10_000]); // must be after \EasyCorp\Bundle\EasyAdminBundle\Field\Configurator\CommonPreConfigurator
    }
}
