<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Field;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use Psl\Str;

use function is_callable;
use function Psl\invariant;

final readonly class CallbackConfigurableField
{
    public const OPTION_CALLBACK_PRE_CONFIGURATOR  = 'callbackConfigurableField-callbackPreConfigurator';
    public const OPTION_CALLBACK_CONFIGURATOR      = 'callbackConfigurableField-callbackConfigurator';
    public const OPTION_CALLBACK_POST_CONFIGURATOR = 'callbackConfigurableField-callbackPostConfigurator';

    /**
     * @param TField            $field
     * @param callable(FieldDto $field, EntityDto $entityDto, AdminContext $context):void $configurator
     *
     * @return TField
     *
     * @template TField of FieldInterface
     */
    public static function forFieldWithPreConfigurator(
        FieldInterface $field,
        callable $configurator,
    ): FieldInterface {
        $field->getAsDto()->setCustomOption(self::OPTION_CALLBACK_PRE_CONFIGURATOR, $configurator);

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
    public static function forFieldWithConfigurator(
        FieldInterface $field,
        callable $configurator,
    ): FieldInterface {
        $field->getAsDto()->setCustomOption(self::OPTION_CALLBACK_CONFIGURATOR, $configurator);

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
    public static function forFieldWithPostConfigurator(
        FieldInterface $field,
        callable $configurator,
    ): FieldInterface {
        $field->getAsDto()->setCustomOption(self::OPTION_CALLBACK_POST_CONFIGURATOR, $configurator);

        return $field;
    }

    public static function fieldHasCallbackPreConfigurator(FieldDto $field): bool
    {
        return $field->getCustomOption(self::OPTION_CALLBACK_PRE_CONFIGURATOR) !== null;
    }

    public static function fieldHasCallbackConfigurator(FieldDto $field): bool
    {
        return $field->getCustomOption(self::OPTION_CALLBACK_CONFIGURATOR) !== null;
    }

    public static function fieldHasCallbackPostConfigurator(FieldDto $field): bool
    {
        return $field->getCustomOption(self::OPTION_CALLBACK_POST_CONFIGURATOR) !== null;
    }

    /** @return callable(FieldDto $field, EntityDto $entityDto, AdminContext $context):void */
    public static function getCallbackPreConfigurator(FieldDto $field): callable
    {
        $callable = $field->getCustomOption(self::OPTION_CALLBACK_PRE_CONFIGURATOR);

        invariant(
            is_callable($callable),
            Str\format(
                'Field "%s" option is not callable.',
                self::OPTION_CALLBACK_PRE_CONFIGURATOR,
            ),
        );

        return $callable;
    }

    /** @return callable(FieldDto $field, EntityDto $entityDto, AdminContext $context):void */
    public static function getCallbackConfigurator(FieldDto $field): callable
    {
        $callable = $field->getCustomOption(self::OPTION_CALLBACK_CONFIGURATOR);

        invariant(
            is_callable($callable),
            Str\format(
                'Field "%s" option is not callable.',
                self::OPTION_CALLBACK_CONFIGURATOR,
            ),
        );

        return $callable;
    }

    /** @return callable(FieldDto $field, EntityDto $entityDto, AdminContext $context):void */
    public static function getCallbackPostConfigurator(FieldDto $field): callable
    {
        $callable = $field->getCustomOption(self::OPTION_CALLBACK_POST_CONFIGURATOR);

        invariant(
            is_callable($callable),
            Str\format(
                'Field "%s" option is not callable.',
                self::OPTION_CALLBACK_POST_CONFIGURATOR,
            ),
        );

        return $callable;
    }
}
