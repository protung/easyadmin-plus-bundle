<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Field\Decorator;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use Protung\EasyAdminPlusBundle\Field\CallbackConfigurableFieldOption;

final class CallbackConfigurableField
{
    /**
     * @param TField            $field
     * @param callable(FieldDto $field, EntityDto $entityDto, AdminContext $context):void $configurator
     *
     * @return TField
     *
     * @template TField of FieldInterface
     */
    public static function beforeCommonPreConfigurator(FieldInterface $field, callable $configurator): FieldInterface
    {
        $field->getAsDto()->setCustomOption(CallbackConfigurableFieldOption::CallbackBeforeCommonPreConfigurator->value, $configurator);

        return $field;
    }

    /**
     * @param TField            $field
     * @param callable(FieldDto $field, EntityDto $entityDto, AdminContext $context):void $configurator
     *
     * @return TField
     *
     * @template TField of FieldInterface
     */
    public static function new(FieldInterface $field, callable $configurator): FieldInterface
    {
        $field->getAsDto()->setCustomOption(CallbackConfigurableFieldOption::CallbackConfigurator->value, $configurator);

        return $field;
    }

    /**
     * @param TField            $field
     * @param callable(FieldDto $field, EntityDto $entityDto, AdminContext $context):void $configurator
     *
     * @return TField
     *
     * @template TField of FieldInterface
     */
    public static function afterCommonPostConfigurator(FieldInterface $field, callable $configurator): FieldInterface
    {
        $field->getAsDto()->setCustomOption(CallbackConfigurableFieldOption::CallbackAfterCommonPostConfigurator->value, $configurator);

        return $field;
    }
}
