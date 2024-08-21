<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Field;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use Psl\Str;

use function is_callable;
use function Psl\invariant;

enum CallbackConfigurableFieldOption: string
{
    case CallbackBeforeCommonPreConfigurator = 'callbackConfigurable-callbackBeforePreConfigurator';
    case CallbackConfigurator                = 'callbackConfigurable-callbackConfigurator';
    case CallbackAfterCommonPostConfigurator = 'callbackConfigurable-callbackAfterPostConfigurator';

    public static function fieldHasCallbackBeforeCommonPreConfigurator(FieldDto $field): bool
    {
        return $field->getCustomOption(self::CallbackBeforeCommonPreConfigurator->value) !== null;
    }

    public static function fieldHasCallbackConfigurator(FieldDto $field): bool
    {
        return $field->getCustomOption(self::CallbackConfigurator->value) !== null;
    }

    public static function fieldHasCallbackAfterCommonPostConfigurator(FieldDto $field): bool
    {
        return $field->getCustomOption(self::CallbackAfterCommonPostConfigurator->value) !== null;
    }

    /** @return callable(FieldDto $field, EntityDto $entityDto, AdminContext $context):void */
    public static function getCallbackBeforeCommonPreConfigurator(FieldDto $field): callable
    {
        $callable = $field->getCustomOption(self::CallbackBeforeCommonPreConfigurator->value);

        invariant(
            is_callable($callable),
            Str\format(
                'Field "%s" option is not callable.',
                self::CallbackBeforeCommonPreConfigurator->value,
            ),
        );

        return $callable;
    }

    /** @return callable(FieldDto $field, EntityDto $entityDto, AdminContext $context):void */
    public static function getCallbackConfigurator(FieldDto $field): callable
    {
        $callable = $field->getCustomOption(self::CallbackConfigurator->value);

        invariant(
            is_callable($callable),
            Str\format(
                'Field "%s" option is not callable.',
                self::CallbackConfigurator->value,
            ),
        );

        return $callable;
    }

    /** @return callable(FieldDto $field, EntityDto $entityDto, AdminContext $context):void */
    public static function getCallbackAfterCommonPostConfigurator(FieldDto $field): callable
    {
        $callable = $field->getCustomOption(self::CallbackAfterCommonPostConfigurator->value);

        invariant(
            is_callable($callable),
            Str\format(
                'Field "%s" option is not callable.',
                self::CallbackAfterCommonPostConfigurator->value,
            ),
        );

        return $callable;
    }
}
