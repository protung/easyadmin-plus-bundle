<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Form\DataTransformer;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

use function is_object;

final class EntityFieldDataTransformer implements DataTransformerInterface
{
    private EntityManagerInterface $entityManager;

    private PropertyAccessorInterface $propertyAccessor;

    /** @var class-string */
    private string $class;

    /**
     * @param class-string $class
     */
    public function __construct(EntityManagerInterface $entityManager, PropertyAccessorInterface $propertyAccessor, string $class)
    {
        $this->entityManager    = $entityManager;
        $this->propertyAccessor = $propertyAccessor;
        $this->class            = $class;
    }

    /**
     * {@inheritDoc}
     */
    public function transform($value): object|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->entityManager->find($this->class, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function reverseTransform($value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_object($value)) {
            throw new TransformationFailedException('Expected an object.');
        }

        return $this->propertyAccessor->getValue(
            $value,
            $this->entityManager->getClassMetadata($this->class)->getSingleIdentifierFieldName(),
        );
    }
}
