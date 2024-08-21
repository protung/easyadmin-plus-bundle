<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Field;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;

trait CallbackConfigurableField
{
    /**
     * @param callable(FieldDto $field, EntityDto $entityDto, AdminContext $context):void $configurator
     */
    public function setCallbackConfiguratorBeforeCommonPreConfigurator(callable $configurator): self
    {
        $this->setCustomOption(CallbackConfigurableFieldOption::CallbackBeforeCommonPreConfigurator->value, $configurator);

        return $this;
    }

    /**
     * @param callable(FieldDto $field, EntityDto $entityDto, AdminContext $context):void $configurator
     */
    public function setCallbackConfigurator(callable $configurator): self
    {
        $this->setCustomOption(CallbackConfigurableFieldOption::CallbackConfigurator->value, $configurator);

        return $this;
    }

    /**
     * @param callable(FieldDto $field, EntityDto $entityDto, AdminContext $context):void $configurator
     */
    public function setCallbackConfiguratorAfterCommonPostConfigurator(callable $configurator): self
    {
        $this->setCustomOption(CallbackConfigurableFieldOption::CallbackAfterCommonPostConfigurator->value, $configurator);

        return $this;
    }
}
