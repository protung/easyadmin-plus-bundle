<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Form\Type;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\CrudAutocompleteType as EasyAdminCrudAutocompleteType;
use Override;
use Protung\EasyAdminPlusBundle\Form\DataTransformer\EntityFieldDataTransformer;
use Psl\Class;
use Psl\Type;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

use function Psl\invariant;

final class CrudAutocompleteType extends AbstractType
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PropertyAccessorInterface $propertyAccessor,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $class = Type\string()->coerce($options['class']);
        invariant(Class\exists($class), 'Option "class" is not a class.');
        $multiple = Type\bool()->coerce($options['multiple']);

        if ($options['is_association'] === false) {
            $builder->addModelTransformer(
                new EntityFieldDataTransformer(
                    $this->entityManager,
                    $this->propertyAccessor,
                    $class,
                    $multiple,
                ),
            );
        }
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('is_association', false);
        $resolver->setAllowedTypes('is_association', 'bool');
        $resolver->define('choice_label');
        $resolver->define('placeholder');
    }

    #[Override]
    public function getParent(): string
    {
        return EasyAdminCrudAutocompleteType::class;
    }
}
