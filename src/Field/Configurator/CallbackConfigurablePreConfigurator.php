<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Field\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use Override;
use Protung\EasyAdminPlusBundle\Field\CallbackConfigurableField;

final class CallbackConfigurablePreConfigurator implements FieldConfiguratorInterface
{
    #[Override]
    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return CallbackConfigurableField::fieldHasCallbackPreConfigurator($field);
    }

    #[Override]
    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $configurator = CallbackConfigurableField::getCallbackPreConfigurator($field);

        $configurator($field, $entityDto, $context);
    }
}
