<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\DependencyInjection\EasyAdminExtension;
use Symfony\Component\Form\FormTypeInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()->private()->autowire()
        ->instanceof(FieldConfiguratorInterface::class)->tag(EasyAdminExtension::TAG_FIELD_CONFIGURATOR)
        ->instanceof(FormTypeInterface::class)->tag('form.type');

    $services
        ->load('Protung\\EasyAdminPlusBundle\\', '../../../src/*')
        ->exclude('../../../src/Resources/**/*');
};
