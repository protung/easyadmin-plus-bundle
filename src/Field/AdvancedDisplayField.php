<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Field;

use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use Psl\Str;
use Psl\Type;
use RuntimeException;
use Stringable;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

use function htmlspecialchars;
use function is_callable;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

trait AdvancedDisplayField
{
    public const string OPTION_ENTITY_DISPLAY_FIELD = 'entityDisplayField';

    /**
     * @param string|(callable(TEntity):string|TranslatableInterface) $entityDisplayField
     *
     * @template TEntity of object
     */
    public function setEntityDisplayField(string|callable $entityDisplayField): self
    {
        $this->setCustomOption(self::OPTION_ENTITY_DISPLAY_FIELD, $entityDisplayField);

        return $this;
    }

    /**
     * @return string|(callable(object):string|TranslatableInterface)|null
     */
    public static function getEntityDisplayField(FieldDto $field): string|callable|null
    {
        /** @var string|(callable(object):string|TranslatableInterface)|null $value */
        $value = $field->getCustomOption(self::OPTION_ENTITY_DISPLAY_FIELD);

        if (is_callable($value)) {
            return $value;
        }

        return Type\nullable(Type\string())->coerce($value);
    }

    public static function formatAsString(object|null $entityInstance, FieldDto $field, TranslatorInterface $translator, Environment $twig): string|null
    {
        if ($entityInstance === null) {
            return null;
        }

        $targetEntityDisplayField = self::getEntityDisplayField($field);
        if ($targetEntityDisplayField !== null) {
            if (is_callable($targetEntityDisplayField)) {
                $displayValue = $targetEntityDisplayField($entityInstance);

                return $displayValue instanceof TranslatableInterface ? $displayValue->trans($translator) : $displayValue;
            }

            return Type\nullable(Type\string())->coerce(PropertyAccess::createPropertyAccessor()->getValue($entityInstance, $targetEntityDisplayField));
        }

        if ($entityInstance instanceof Stringable) {
            return (string) $entityInstance;
        }

        // This is only for autocomplete fields.
        $twigTemplate = $field->getCustomOption(EntityField::OPTION_AUTOCOMPLETE_TEMPLATE);
        if ($twigTemplate !== null) {
            $renderAsHtml   = $field->getCustomOption(EntityField::OPTION_ESCAPE_HTML_CONTENTS) === false;
            $entityAsString = $twig->render(Type\string()->coerce($twigTemplate), ['entity' => $entityInstance]);
            if (! $renderAsHtml) {
                $entityAsString = htmlspecialchars($entityAsString, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }

            return $entityAsString;
        }

        throw new RuntimeException(
            Str\format(
                'The "%s" field cannot be configured because it does not define the related entity display value set with the "setEntityDisplayField()" method. or implement "__toString()".',
                $field->getProperty(),
            ),
        );
    }
}
