<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Orm;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\SearchMode;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Orm\EntityRepositoryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntitySearchEvent;
use EasyCorp\Bundle\EasyAdminBundle\Factory\EntityFactory;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FormFactory;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonType;
use EasyCorp\Bundle\EasyAdminBundle\Orm\Escaper;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Registry\CrudControllerRegistry;
use InvalidArgumentException;
use Protung\EasyAdminPlusBundle\Field\EntityField;
use Psl\Str;
use Psl\Type;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

use function assert;
use function class_exists;
use function count;
use function ctype_digit;
use function current;
use function explode;
use function in_array;
use function is_array;
use function is_numeric;
use function is_subclass_of;
use function mb_strtolower;
use function sprintf;

/**
 * This class is fully copied from \EasyCorp\Bundle\EasyAdminBundle\Orm\EntityRepository with code style fixes applied.
 * The only change is to add support for searching by \Protung\EasyAdminPlusBundle\Field\EntityField.
 * Because most of the logic is in the private methods, we cannot add this support in a different way.
 */
final readonly class EntityRepository implements EntityRepositoryInterface
{
    public function __construct(
        private AdminContextProvider $adminContextProvider,
        private ManagerRegistry $doctrine,
        private EntityFactory $entityFactory,
        private FormFactory $formFactory,
        private EventDispatcherInterface $eventDispatcher,
        private CrudControllerRegistry $crudControllerRegistry,
    ) {
    }

    public function createQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $entityManager = $this->doctrine->getManagerForClass($entityDto->getFqcn());
        assert($entityManager instanceof EntityManagerInterface);
        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('entity')
            ->from($entityDto->getFqcn(), 'entity');

        if ($searchDto->getQuery() !== '') {
            try {
                $databasePlatform = $entityManager->getConnection()->getDatabasePlatform();
            } catch (Throwable) {
                $databasePlatform = null;
            }

            $databasePlatformFqcn = $databasePlatform !== null ? $databasePlatform::class : '';

            $this->addSearchClause($queryBuilder, $searchDto, $entityDto, $databasePlatformFqcn, $fields);
        }

        $appliedFilters = $searchDto->getAppliedFilters();
        if ($appliedFilters !== null && count($appliedFilters) !== 0) {
            $this->addFilterClause($queryBuilder, $searchDto, $entityDto, $filters, $fields);
        }

        $this->addOrderClause($queryBuilder, $searchDto, $entityDto);

        return $queryBuilder;
    }

    private function addSearchClause(QueryBuilder $queryBuilder, SearchDto $searchDto, EntityDto $entityDto, string $databasePlatformFqcn, FieldCollection $fields): void
    {
        $isPostgreSql               = $databasePlatformFqcn === PostgreSQLPlatform::class || is_subclass_of($databasePlatformFqcn, PostgreSQLPlatform::class);
        $searchablePropertiesConfig = $this->getSearchablePropertiesConfig($queryBuilder, $searchDto, $entityDto, $fields);

        $queryTerms     = $searchDto->getQueryTerms();
        $queryTermIndex = 0;
        foreach ($queryTerms as $queryTerm) {
            ++$queryTermIndex;

            $lowercaseQueryTerm      = mb_strtolower($queryTerm);
            $isNumericQueryTerm      = is_numeric($queryTerm);
            $isSmallIntegerQueryTerm = ctype_digit($queryTerm) && $queryTerm >= -32768 && $queryTerm <= 32767;
            $isIntegerQueryTerm      = ctype_digit($queryTerm) && $queryTerm >= -2147483648 && $queryTerm <= 2147483647;
            $isUuidQueryTerm         = Uuid::isValid($queryTerm);
            $isUlidQueryTerm         = Ulid::isValid($queryTerm);

            $dqlParameters = [
                // adding '0' turns the string into a numeric value
                'numeric_query' => is_numeric($queryTerm) ? 0 + $queryTerm : $queryTerm,
                'uuid_query' => $queryTerm,
                'text_query' => '%' . $lowercaseQueryTerm . '%',
            ];

            $queryTermConditions = new Orx();
            foreach ($searchablePropertiesConfig as $propertyConfig) {
                $entityName = $propertyConfig['entity_name'];

                // this complex condition is needed to avoid issues on PostgreSQL databases
                if (
                    ($propertyConfig['is_small_integer'] && $isSmallIntegerQueryTerm)
                    || ($propertyConfig['is_integer'] && $isIntegerQueryTerm)
                    || ($propertyConfig['is_numeric'] && $isNumericQueryTerm)
                ) {
                    $parameterName = sprintf('query_for_numbers_%d', $queryTermIndex);
                    $queryTermConditions->add(sprintf('%s.%s = :%s', $entityName, $propertyConfig['property_name'], $parameterName));
                    $queryBuilder->setParameter($parameterName, $dqlParameters['numeric_query']);
                } elseif ($propertyConfig['is_guid'] && $isUuidQueryTerm) {
                    $parameterName = sprintf('query_for_uuids_%d', $queryTermIndex);
                    $queryTermConditions->add(sprintf('%s.%s = :%s', $entityName, $propertyConfig['property_name'], $parameterName));
                    $queryBuilder->setParameter($parameterName, $dqlParameters['uuid_query'], $propertyConfig['property_data_type'] === 'uuid' ? 'uuid' : null);
                } elseif ($propertyConfig['is_ulid'] && $isUlidQueryTerm) {
                    $parameterName = sprintf('query_for_ulids_%d', $queryTermIndex);
                    $queryTermConditions->add(sprintf('%s.%s = :%s', $entityName, $propertyConfig['property_name'], $parameterName));
                    $queryBuilder->setParameter($parameterName, $dqlParameters['uuid_query'], 'ulid');
                } elseif ($propertyConfig['is_text']) {
                    $parameterName = sprintf('query_for_text_%d', $queryTermIndex);
                    $queryTermConditions->add(sprintf('LOWER(%s.%s) LIKE :%s', $entityName, $propertyConfig['property_name'], $parameterName));
                    $queryBuilder->setParameter($parameterName, $dqlParameters['text_query']);
                } elseif ($propertyConfig['is_json'] && ! $isPostgreSql) {
                    // neither LOWER() nor LIKE() are supported for JSON columns by all PostgreSQL installations
                    $parameterName = sprintf('query_for_text_%d', $queryTermIndex);
                    $queryTermConditions->add(sprintf('LOWER(%s.%s) LIKE :%s', $entityName, $propertyConfig['property_name'], $parameterName));
                    $queryBuilder->setParameter($parameterName, $dqlParameters['text_query']);
                }
            }

            if ($searchDto->getSearchMode() === SearchMode::ALL_TERMS) {
                $queryBuilder->andWhere($queryTermConditions);
            } else {
                $queryBuilder->orWhere($queryTermConditions);
            }
        }

        $this->eventDispatcher->dispatch(new AfterEntitySearchEvent($queryBuilder, $searchDto, $entityDto));
    }

    private function addOrderClause(QueryBuilder $queryBuilder, SearchDto $searchDto, EntityDto $entityDto): void
    {
        foreach ($searchDto->getSort() as $sortProperty => $sortOrder) {
            $aliases                        = $queryBuilder->getAllAliases();
            $sortFieldIsDoctrineAssociation = $entityDto->isAssociation($sortProperty);

            if ($sortFieldIsDoctrineAssociation) {
                $sortFieldParts = explode('.', $sortProperty, 2);
                // check if join has been added once before.
                if (! in_array($sortFieldParts[0], $aliases, true)) {
                    $queryBuilder->leftJoin('entity.' . $sortFieldParts[0], $sortFieldParts[0]);
                }

                if (count($sortFieldParts) === 1) {
                    if ($entityDto->isToManyAssociation($sortProperty)) {
                        $metadata = $entityDto->getPropertyMetadata($sortProperty);

                        $entityManager = $this->doctrine->getManagerForClass($entityDto->getFqcn());
                        assert($entityManager instanceof EntityManagerInterface);
                        $countQueryBuilder = $entityManager->createQueryBuilder();

                        if ($metadata->get('type') === ClassMetadataInfo::MANY_TO_MANY) {
                            // many-to-many relation
                            $countQueryBuilder
                                ->select($queryBuilder->expr()->count('subQueryEntity'))
                                ->from($entityDto->getFqcn(), 'subQueryEntity')
                                ->join(sprintf('subQueryEntity.%s', $sortProperty), 'relatedEntity')
                                ->where('subQueryEntity = entity');
                        } else {
                            // one-to-many relation
                            $countQueryBuilder
                                ->select($queryBuilder->expr()->count('subQueryEntity'))
                                ->from($metadata->get('targetEntity'), 'subQueryEntity')
                                ->where(sprintf('subQueryEntity.%s = entity', $metadata->get('mappedBy')));
                        }

                        $queryBuilder->addSelect(sprintf('(%s) as HIDDEN sub_query_sort', $countQueryBuilder->getDQL()));
                        $queryBuilder->addOrderBy('sub_query_sort', $sortOrder);
                        $queryBuilder->addOrderBy('entity.' . $entityDto->getPrimaryKeyName(), $sortOrder);
                    } else {
                        $queryBuilder->addOrderBy('entity.' . $sortProperty, $sortOrder);
                    }
                } else {
                    $queryBuilder->addOrderBy($sortProperty, $sortOrder);
                }
            } else {
                $queryBuilder->addOrderBy('entity.' . $sortProperty, $sortOrder);
            }
        }
    }

    private function addFilterClause(QueryBuilder $queryBuilder, SearchDto $searchDto, EntityDto $entityDto, FilterCollection $configuredFilters, FieldCollection $fields): void
    {
        $filtersForm = $this->formFactory->createFiltersForm($configuredFilters, $this->adminContextProvider->getContext()->getRequest());
        if (! $filtersForm->isSubmitted()) {
            return;
        }

        $appliedFilters = $searchDto->getAppliedFilters();
        $i              = 0;
        foreach ($filtersForm as $filterForm) {
            $propertyName = $filterForm->getName();

            $filter = $configuredFilters->get($propertyName);
            // this filter is not defined or not applied
            if ($filter === null || ! isset($appliedFilters[$propertyName])) {
                continue;
            }

            // if the form filter is not valid then we should not apply the filter
            if (! $filterForm->isValid()) {
                continue;
            }

            $submittedData = $filterForm->getData();
            if (! is_array($submittedData)) {
                $submittedData = [
                    'comparison' => ComparisonType::EQ,
                    'value' => $submittedData,
                ];
            }

            $filterDataDto = FilterDataDto::new($i, $filter, current($queryBuilder->getRootAliases()), $submittedData);
            $filter->apply($queryBuilder, $filterDataDto, $fields->getByProperty($propertyName), $entityDto);

            ++$i;
        }
    }

    /**
     * @return list<array<mixed>>
     */
    protected function getSearchablePropertiesConfig(QueryBuilder $queryBuilder, SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields): array
    {
        $searchablePropertiesConfig     = [];
        $configuredSearchableProperties = $searchDto->getSearchableProperties();
        $searchableProperties           = $configuredSearchableProperties === null || count($configuredSearchableProperties) === 0 ? $entityDto->getAllPropertyNames() : $configuredSearchableProperties;

        $entitiesAlreadyJoined = [];
        foreach ($searchableProperties as $propertyName) {
            if ($entityDto->isAssociation($propertyName)) {
                // support arbitrarily nested associations (e.g. foo.bar.baz.qux)
                $associatedProperties    = explode('.', $propertyName);
                $numAssociatedProperties = count($associatedProperties);

                if ($numAssociatedProperties === 1) {
                    throw new InvalidArgumentException(sprintf('The "%s" property included in the setSearchFields() method is not a valid search field. When using associated properties in search, you must also define the exact field used in the search (e.g. \'%s.id\', \'%s.name\', etc.)', $propertyName, $propertyName, $propertyName));
                }

                $originalPropertyName     = $associatedProperties[0];
                $originalPropertyMetadata = $entityDto->getPropertyMetadata($originalPropertyName);

                $field = $fields->getByProperty($originalPropertyName);
                if ($field !== null && $field->getFieldFqcn() === EntityField::class) {
                    $targetCrudControllerFqcn = Type\string()->coerce($field->getCustomOption(EntityField::OPTION_CRUD_CONTROLLER));

                    $targetEntityFqcn = $this->crudControllerRegistry->findEntityFqcnByCrudFqcn($targetCrudControllerFqcn);
                } else {
                    $targetEntityFqcn = $originalPropertyMetadata->get('targetEntity');
                }

                $associatedEntityDto = $this->entityFactory->create($targetEntityFqcn);

                $associatedEntityAlias = $associatedPropertyName = '';
                for ($i = 0; $i < $numAssociatedProperties - 1; ++$i) {
                    $associatedEntityName   = $associatedProperties[$i];
                    $associatedEntityAlias  = Escaper::escapeDqlAlias($associatedEntityName);
                    $associatedPropertyName = $associatedProperties[$i + 1];

                    if (! in_array($associatedEntityName, $entitiesAlreadyJoined, true)) {
                        $parentEntityName = $i === 0 ? 'entity' : $associatedProperties[$i - 1];

                        if ($field !== null && $field->getFieldFqcn() === EntityField::class) {
                            $associatedEntityPrimaryKeyName = Type\string()->coerce($associatedEntityDto->getPrimaryKeyName());

                            $queryBuilder->leftJoin(
                                $targetEntityFqcn,
                                $originalPropertyName,
                                Join::WITH,
                                Str\format(
                                    '%s.%s = %s.%s',
                                    $parentEntityName,
                                    $associatedEntityName,
                                    $originalPropertyName,
                                    $associatedEntityPrimaryKeyName,
                                ),
                            );
                        } else {
                            $queryBuilder->leftJoin(Escaper::escapeDqlAlias($parentEntityName) . '.' . $associatedEntityName, $associatedEntityAlias);
                        }

                        $entitiesAlreadyJoined[] = $associatedEntityName;
                    }

                    if ($i >= $numAssociatedProperties - 2) {
                        continue;
                    }

                    $propertyMetadata    = $associatedEntityDto->getPropertyMetadata($associatedPropertyName);
                    $targetEntity        = $propertyMetadata->get('targetEntity');
                    $associatedEntityDto = $this->entityFactory->create($targetEntity);
                }

                $entityName       = $associatedEntityAlias;
                $propertyName     = $associatedPropertyName;
                $propertyDataType = $associatedEntityDto->getPropertyDataType($propertyName);
            } else {
                $entityName       = 'entity';
                $propertyDataType = $entityDto->getPropertyDataType($propertyName);
            }

            $isBoolean              = $propertyDataType === 'boolean';
            $isSmallIntegerProperty = $propertyDataType === 'smallint';
            $isIntegerProperty      = $propertyDataType === 'integer';
            $isNumericProperty      = in_array($propertyDataType, ['number', 'bigint', 'decimal', 'float'], true);
            // 'citext' is a PostgreSQL extension (https://github.com/EasyCorp/EasyAdminBundle/issues/2556)
            $isTextProperty = in_array($propertyDataType, ['string', 'text', 'citext', 'array', 'simple_array'], true);
            $isGuidProperty = in_array($propertyDataType, ['guid', 'uuid'], true);
            $isUlidProperty = $propertyDataType === 'ulid';
            $isJsonProperty = $propertyDataType === 'json';

            if (
                ! $isBoolean
                && ! $isSmallIntegerProperty
                && ! $isIntegerProperty
                && ! $isNumericProperty
                && ! $isTextProperty
                && ! $isGuidProperty
                && ! $isUlidProperty
                && ! $isJsonProperty
            ) {
                $entityFqcn  = $entityName !== 'entity' && isset($associatedEntityDto)
                    ? $associatedEntityDto->getFqcn()
                    : $entityDto->getFqcn();
                $idClassType = (new ReflectionProperty($entityFqcn, $propertyName))->getType();
                assert($idClassType instanceof ReflectionNamedType || $idClassType instanceof ReflectionUnionType || $idClassType === null);

                if ($idClassType !== null) {
                    $idClassName = $idClassType->getName();

                    if (class_exists($idClassName)) {
                        $isUlidProperty = (new ReflectionClass($idClassName))->isSubclassOf(Ulid::class);
                        $isGuidProperty = (new ReflectionClass($idClassName))->isSubclassOf(Uuid::class);
                    }
                }
            }

            $searchablePropertiesConfig[] = [
                'entity_name' => $entityName,
                'property_data_type' => $propertyDataType,
                'property_name' => $propertyName,
                'is_boolean' => $isBoolean,
                'is_small_integer' => $isSmallIntegerProperty,
                'is_integer' => $isIntegerProperty,
                'is_numeric' => $isNumericProperty,
                'is_text' => $isTextProperty,
                'is_guid' => $isGuidProperty,
                'is_ulid' => $isUlidProperty,
                'is_json' => $isJsonProperty,
            ];
        }

        return $searchablePropertiesConfig;
    }
}
