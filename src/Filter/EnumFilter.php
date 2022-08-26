<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Filter;

use BackedEnum;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Contracts\Translation\TranslatableInterface;

final class EnumFilter
{
    /**
     * @param class-string<BackedEnum> $enum
     */
    public static function new(string $property, string $enum, TranslatableInterface|string|false|null $label = null): ChoiceFilter
    {
        return ChoiceFilter::new($property, $label)
            ->setChoices($enum::cases())
            ->setFormTypeOption('value_type', EnumType::class)
            ->setFormTypeOption('value_type_options.class', $enum);
    }
}
