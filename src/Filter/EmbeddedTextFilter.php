<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\TextFilterType;
use Override;
use Psl\Str;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Filter that supports doctrine embeddable properties.
 */
final class EmbeddedTextFilter implements FilterInterface
{
    use FilterTrait;

    private const PROPERTY_PATH_SEPARATOR = '___';

    public static function new(string $propertyName, TranslatableInterface|string|false|null $label = null): self
    {
        return (new self())
            ->setFilterFqcn(self::class)
            ->setProperty(Str\replace($propertyName, '.', self::PROPERTY_PATH_SEPARATOR))
            ->setLabel($label)
            ->setFormType(TextFilterType::class)
            ->setFormTypeOption('translation_domain', 'EasyAdminBundle');
    }

    #[Override]
    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, FieldDto|null $fieldDto, EntityDto $entityDto): void
    {
        $alias         = $filterDataDto->getEntityAlias();
        $property      = $filterDataDto->getProperty();
        $comparison    = $filterDataDto->getComparison();
        $parameterName = $filterDataDto->getParameterName();
        $value         = $filterDataDto->getValue();

        if ($value === null) {
            return;
        }

        $propertyPath = Str\replace($property, self::PROPERTY_PATH_SEPARATOR, '.');

        $queryBuilder->andWhere(Str\format('%s.%s %s :%s', $alias, $propertyPath, $comparison, $parameterName))
            ->setParameter($parameterName, $value);
    }
}
