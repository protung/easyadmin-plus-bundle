<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Form\DataTransformer;

use Doctrine\ORM\EntityManagerInterface;
use Psl\Dict;
use Psl\Type;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

use function is_iterable;
use function is_object;

final readonly class EntityFieldDataTransformer implements DataTransformerInterface
{
    /**
     * @param class-string $class
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PropertyAccessorInterface $propertyAccessor,
        private string $class,
        private bool $multiple,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function transform($value): object|array|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($this->multiple) {
            if (! is_iterable($value)) {
                throw new UnexpectedTypeException($value, 'iterable');
            }

            return Dict\map(
                $value,
                fn (mixed $id): object|null => $this->entityManager->find($this->class, $id),
            );
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

        if ($this->multiple) {
            if (! Type\iterable(Type\array_key(), Type\object())->matches($value)) {
                throw new UnexpectedTypeException($value, 'iterable');
            }

            return Dict\map(
                $value,
                fn (object $entity): mixed => $this->propertyAccessor->getValue(
                    $entity,
                    $this->entityManager->getClassMetadata($this->class)->getSingleIdentifierFieldName(),
                ),
            );
        }

        if (! is_object($value)) {
            throw new UnexpectedTypeException($value, 'object');
        }

        return $this->propertyAccessor->getValue(
            $value,
            $this->entityManager->getClassMetadata($this->class)->getSingleIdentifierFieldName(),
        );
    }
}
