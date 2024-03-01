<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Orm;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Orm\EntityPaginatorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\PaginatorDto;
use EasyCorp\Bundle\EasyAdminBundle\Factory\ControllerFactory;
use EasyCorp\Bundle\EasyAdminBundle\Factory\EntityFactory;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Override;
use Protung\EasyAdminPlusBundle\Field\EntityField;
use Psl\Dict;
use Psl\Json;
use Psl\Type;
use Psl\Type\Exception\CoercionException;
use Psl\Vec;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

use function is_callable;

final readonly class EntityPaginator implements EntityPaginatorInterface
{
    public function __construct(
        private EntityPaginatorInterface $decoratedPaginator,
        private AdminContextProvider $adminContextProvider,
        private ControllerFactory $controllerFactory,
        private EntityFactory $entityFactory,
        private AdminUrlGeneratorInterface $adminUrlGenerator,
        private PropertyAccessorInterface $propertyAccessor,
    ) {
    }

    #[Override]
    public function paginate(PaginatorDto $paginatorDto, QueryBuilder $queryBuilder): EntityPaginatorInterface
    {
        return new self(
            $this->decoratedPaginator->paginate($paginatorDto, $queryBuilder),
            $this->adminContextProvider,
            $this->controllerFactory,
            $this->entityFactory,
            $this->adminUrlGenerator,
            $this->propertyAccessor,
        );
    }

    #[Override]
    public function generateUrlForPage(int $page): string
    {
        return $this->decoratedPaginator->generateUrlForPage($page);
    }

    #[Override]
    public function getCurrentPage(): int
    {
        return $this->decoratedPaginator->getCurrentPage();
    }

    #[Override]
    public function getLastPage(): int
    {
        return $this->decoratedPaginator->getLastPage();
    }

    /**
     * @return iterable<mixed>
     */
    #[Override]
    public function getPageRange(int|null $pagesOnEachSide = null, int|null $pagesOnEdges = null): iterable
    {
        return $this->decoratedPaginator->getPageRange($pagesOnEachSide, $pagesOnEdges);
    }

    #[Override]
    public function getPageSize(): int
    {
        return $this->decoratedPaginator->getPageSize();
    }

    #[Override]
    public function hasPreviousPage(): bool
    {
        return $this->decoratedPaginator->hasPreviousPage();
    }

    #[Override]
    public function getPreviousPage(): int
    {
        return $this->decoratedPaginator->getPreviousPage();
    }

    #[Override]
    public function hasNextPage(): bool
    {
        return $this->decoratedPaginator->hasNextPage();
    }

    #[Override]
    public function getNextPage(): int
    {
        return $this->decoratedPaginator->getNextPage();
    }

    #[Override]
    public function hasToPaginate(): bool
    {
        return $this->decoratedPaginator->hasToPaginate();
    }

    #[Override]
    public function isOutOfRange(): bool
    {
        return $this->decoratedPaginator->isOutOfRange();
    }

    #[Override]
    public function getNumResults(): int
    {
        return $this->decoratedPaginator->getNumResults();
    }

    #[Override]
    public function getResults(): iterable|null
    {
        return $this->decoratedPaginator->getResults();
    }

    #[Override]
    public function getResultsAsJson(): string
    {
        $context = $this->adminContextProvider->getContext();

        if ($context === null) {
            return $this->decoratedPaginator->getResultsAsJson();
        }

        try {
            $autocompleteContext = Type\shape(
                [
                    EA::CRUD_CONTROLLER_FQCN => Type\string(),
                    'propertyName' => Type\string(),
                    'originatingPage' => Type\string(),
                    EntityField::OPTION_ENTITY_DISPLAY_FIELD => Type\bool(),
                ],
            )->coerce($context->getRequest()->query->all(AssociationField::PARAM_AUTOCOMPLETE_CONTEXT));
        } catch (CoercionException) {
            return $this->decoratedPaginator->getResultsAsJson();
        }

        $controller = $this->controllerFactory->getCrudControllerInstance(
            $autocompleteContext[EA::CRUD_CONTROLLER_FQCN],
            Action::INDEX,
            $context->getRequest(),
        );

        if ($controller === null) {
            return $this->decoratedPaginator->getResultsAsJson();
        }

        $fields = Type\vec(Type\instance_of(FieldInterface::class))->coerce($controller->configureFields($autocompleteContext['originatingPage']));
        $field  = Type\instance_of(FieldDto::class)->coerce(FieldCollection::new($fields)->getByProperty($autocompleteContext['propertyName']));

        $targetEntityDisplayField = $this->getEntityDisplayField($field);
        if ($targetEntityDisplayField === null) {
            return $this->decoratedPaginator->getResultsAsJson();
        }

        $primaryKeyName = Type\string()->coerce($context->getEntity()->getPrimaryKeyName());

        $results = $this->decoratedPaginator->getResults();
        if ($results === null) {
            return $this->decoratedPaginator->getResultsAsJson();
        }

        $reindexResults = Dict\reindex(
            Type\vec(Type\object())->coerce($results),
            fn (object $entityInstance): string => (string) $this->propertyAccessor->getValue($entityInstance, $primaryKeyName),
        );

        $generatedJson = Json\typed(
            $this->decoratedPaginator->getResultsAsJson(),
            Type\shape(
                [
                    'results' => Type\vec(
                        Type\shape(
                            [
                                EA::ENTITY_ID => Type\non_empty_string(),
                                'entityAsString' => Type\non_empty_string(),
                            ],
                        ),
                    ),
                    'next_page' => Type\nullable(Type\string()),
                ],
            ),
        );

        $generatedJson['results'] = Vec\map(
            $generatedJson['results'],
            function (array $result) use ($reindexResults, $targetEntityDisplayField): array {
                $entityInstance = Type\object()->coerce($reindexResults[$result[EA::ENTITY_ID]]);

                if (is_callable($targetEntityDisplayField)) {
                    $entityAsString = $targetEntityDisplayField($entityInstance);
                } else {
                    $entityAsString = (string) $this->propertyAccessor->getValue($entityInstance, $targetEntityDisplayField);
                }

                $result['entityAsString'] = $entityAsString;

                return $result;
            },
        );

        return Json\encode($generatedJson);
    }

    /** @return string|(callable(object):?string)|null */
    private function getEntityDisplayField(FieldDto $field): string|callable|null
    {
        /** @var string|(callable(object):?string)|null $value */
        $value = $field->getCustomOption(EntityField::OPTION_ENTITY_DISPLAY_FIELD);

        if (is_callable($value)) {
            return $value;
        }

        return Type\nullable(Type\string())->coerce($value);
    }
}
