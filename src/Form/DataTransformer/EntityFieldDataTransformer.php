<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Form\DataTransformer;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Psl\Dict;
use Psl\Type;
use Stringable;
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
    #[Override]
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
                function (mixed $id): object|null {
                    // If the ID is an object that can be cast to a string, cast it to a string before passing it to the entity manager.
                    // Otherwise, the entity manager will throw an exception because it expects a scalar value for the ID if the identifier is for example an integer.
                    if ($id instanceof Stringable) {
                        $id = (string) $id;
                    }

                    return $this->entityManager->find($this->class, $id);
                },
            );
        }

        // If the ID is an object that can be cast to a string, cast it to a string before passing it to the entity manager.
        // Otherwise, the entity manager will throw an exception because it expects a scalar value for the ID if the identifier is for example an integer.
        if ($value instanceof Stringable) {
            $value = (string) $value;
        }

        return $this->entityManager->find($this->class, $value);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
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
