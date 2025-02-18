<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Override;
use Symfony\Contracts\Translation\TranslatableInterface;

final class JsonField implements FieldInterface
{
    use FieldTrait;
    use CallbackConfigurableField;

    #[Override]
    public static function new(string $propertyName, TranslatableInterface|string|false|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplatePath('@ProtungEasyAdminPlus/crud/field/json.html.twig');
    }
}
