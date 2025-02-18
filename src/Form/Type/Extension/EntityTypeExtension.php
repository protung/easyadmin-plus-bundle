<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Form\Type\Extension;

use Override;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @template-extends AbstractTypeExtension<object|null>
 */
final class EntityTypeExtension extends AbstractTypeExtension
{
    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        // This needs to be defined because it gets passed from CrudAutocompleteType or EntityFieldDoctrineType by the CrudAutocompleteSubscriber
        $resolver->define('is_association');
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public static function getExtendedTypes(): iterable
    {
        return [EntityType::class];
    }
}
