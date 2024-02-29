<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Form\Type;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\CrudAutocompleteType as EasyAdminCrudAutocompleteType;
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
    private EntityManagerInterface $entityManager;

    private PropertyAccessorInterface $propertyAccessor;

    public function __construct(EntityManagerInterface $entityManager, PropertyAccessorInterface $propertyAccessor)
    {
        $this->entityManager    = $entityManager;
        $this->propertyAccessor = $propertyAccessor;
    }

    /**
     * {@inheritDoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $class = Type\string()->coerce($options['class']);
        invariant(Class\exists($class), 'Option "class" is not a class.');

        $builder->addModelTransformer(
            new EntityFieldDataTransformer(
                $this->entityManager,
                $this->propertyAccessor,
                $class,
            ),
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('choice_label');
        $resolver->define('placeholder');
    }

    public function getParent(): string
    {
        return EasyAdminCrudAutocompleteType::class;
    }
}
