<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Field\Configurator;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\CrudDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Factory\EntityFactory;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Protung\EasyAdminPlusBundle\Field\Configurator\EntityConfigurator\EntityMetadata;
use Protung\EasyAdminPlusBundle\Field\EntityField;
use Protung\EasyAdminPlusBundle\Form\Type\CrudAutocompleteType;
use Psl\Class;
use Psl\Str;
use Psl\Type;
use Psl\Vec;
use RuntimeException;
use Stringable;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function is_callable;
use function Psl\invariant;

final class EntityConfigurator implements FieldConfiguratorInterface
{
    private EntityFactory $entityFactory;

    private AdminUrlGenerator $adminUrlGenerator;

    private TranslatorInterface $translator;

    private PropertyAccessorInterface $propertyAccessor;

    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityFactory $entityFactory,
        AdminUrlGenerator $adminUrlGenerator,
        TranslatorInterface $translator,
        PropertyAccessorInterface $propertyAccessor,
        EntityManagerInterface $entityManager,
    ) {
        $this->entityFactory     = $entityFactory;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->translator        = $translator;
        $this->propertyAccessor  = $propertyAccessor;
        $this->entityManager     = $entityManager;
    }

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return $field->getFieldFqcn() === EntityField::class;
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $propertyName = $field->getProperty();

        $targetCrudControllerFqcn = Type\nullable(Type\string())->coerce($field->getCustomOption(EntityField::OPTION_CRUD_CONTROLLER));
        if ($targetCrudControllerFqcn === null) {
            throw new RuntimeException(
                Str\format(
                    'The "%s" field cannot be configured because it doesn\'t define the related CRUD controller FQCN with the "setCrudController()" method.',
                    $field->getProperty(),
                ),
            );
        }

        $crud = Type\instance_of(CrudDto::class)->coerce($context->getCrud());

        $sourceCrudControllerFqcn = Type\string()->coerce($crud->getControllerFqcn());

        $targetEntityFqcn = Type\string()->coerce($context->getCrudControllers()->findEntityFqcnByCrudFqcn($targetCrudControllerFqcn));
        invariant(Class\exists($targetEntityFqcn), 'Could not determine target entity for set CRUD controller.');
        invariant(Class\exists($targetCrudControllerFqcn), 'Could not determine target CRUD controller.');

        $entityMetadata = new EntityMetadata(
            $entityDto->getPrimaryKeyValue(),
            $sourceCrudControllerFqcn,
            $targetCrudControllerFqcn,
            $targetEntityFqcn,
            $this->getEntityDisplayField($field),
            $field->getValue(),
        );

        $this->configureOnChange($field, $entityMetadata);

        $associationType = Type\string()->coerce($field->getCustomOption(EntityField::OPTION_DOCTRINE_ASSOCIATION_TYPE));
        if ($associationType === EntityField::DOCTRINE_ASSOCIATION_TYPE_SINGLE) {
            $this->configureToOneAssociation($field, $entityMetadata);
        } elseif ($associationType === EntityField::DOCTRINE_ASSOCIATION_TYPE_MANY) {
            $this->configureToManyAssociation($field, $entityMetadata, $context);
        } else {
            throw new RuntimeException('Unknown association type.');
        }

        if ($entityMetadata->targetEntityDisplayField() !== null) {
            // even if the entity has __toString method EntityType form type will not use it if option value is set to NULL.
            $field->setFormTypeOption('choice_label', $entityMetadata->targetEntityDisplayField());
        }

        $autocompleteMode = Type\bool()->coerce($field->getCustomOption(EntityField::OPTION_AUTOCOMPLETE));
        $widgetMode       = Type\string()->coerce($field->getCustomOption(EntityField::OPTION_WIDGET));
        if ($widgetMode === EntityField::WIDGET_AUTOCOMPLETE) {
            $field->setFormTypeOption('attr.data-ea-widget', 'ea-autocomplete');
        } elseif ($widgetMode === EntityField::WIDGET_NATIVE) {
            $field->setFormTypeOption('class', $entityMetadata->targetEntityFqcn());
        }

        if ($autocompleteMode === true) {
            if ($widgetMode !== EntityField::WIDGET_AUTOCOMPLETE) {
                throw new RuntimeException(
                    Str\format(
                        'The "%s" field cannot be configured with autocomplete mode for not autocomplete widget .',
                        $field->getProperty(),
                    ),
                );
            }

            $field->setFormType(CrudAutocompleteType::class);
            $autocompleteEndpointUrl = $this->adminUrlGenerator
                ->set('page', 1) // The autocomplete should always start on the first page
                ->setController($entityMetadata->targetCrudControllerFqcn())
                ->setAction('autocomplete')
                ->setEntityId(null)
                ->unset(EA::SORT) // Avoid passing the 'sort' param from the current entity to the autocompleted one
                ->set(EntityField::PARAM_AUTOCOMPLETE_CONTEXT, [
                    EA::CRUD_CONTROLLER_FQCN => $context->getRequest()->query->get(EA::CRUD_CONTROLLER_FQCN),
                    'propertyName' => $propertyName,
                    'originatingPage' => $crud->getCurrentPage(),
                ])
                ->generateUrl();

            $field->setFormTypeOption('attr.data-ea-autocomplete-endpoint-url', $autocompleteEndpointUrl);
        } else {
            $field->setFormTypeOptionIfNotSet('query_builder', static function (EntityRepository $repository) use ($field, $context): QueryBuilder {
                // it would then be identical to the one used in autocomplete action, but it is a bit complex getting it in here
                $queryBuilder         = $repository->createQueryBuilder('entity');
                $queryBuilderCallable = $field->getCustomOption(EntityField::OPTION_QUERY_BUILDER_CALLABLE);
                if ($queryBuilderCallable !== null) {
                    invariant(
                        is_callable($queryBuilderCallable),
                        Str\format('Query builder callable option is not null or callable.'),
                    );
                    $queryBuilderCallable($queryBuilder, $context);
                }

                return $queryBuilder;
            });
        }
    }

    private function configureToOneAssociation(FieldDto $field, EntityMetadata $entityMetadata): void
    {
        if ($field->getFormTypeOption('required') === false) {
            $field->setFormTypeOptionIfNotSet('attr.placeholder', $this->translator->trans('label.form.empty_value', [], 'EasyAdminBundle'));
            $field->setFormTypeOptionIfNotSet('attr.data-allow-clear', true);
        }

        $entityDtoFactoryCallable = $field->getCustomOption(EntityField::OPTION_ENTITY_DTO_FACTORY_CALLABLE);

        $targetEntityDto = null;
        if ($entityDtoFactoryCallable !== null) {
            invariant(
                is_callable($entityDtoFactoryCallable),
                Str\format('EntityDto factory callable option is not null or callable.'),
            );
            $targetEntityDto = Type\nullable(Type\instance_of(EntityDto::class))->coerce($entityDtoFactoryCallable($this->entityFactory, $entityMetadata));
        }

        if ($targetEntityDto === null) {
            $targetEntityDto = $entityMetadata->targetEntityId() === null
                ? $this->entityFactory->create($entityMetadata->targetEntityFqcn())
                : $this->entityFactory->create($entityMetadata->targetEntityFqcn(), $entityMetadata->targetEntityId());
        }

        $targetEntityInstance = Type\nullable(Type\instance_of($entityMetadata->targetEntityFqcn()))->coerce($targetEntityDto->getInstance());

        $field->setFormTypeOptionIfNotSet('class', $targetEntityDto->getFqcn());
        $field->setFormTypeOptionIfNotSet('data', $targetEntityInstance);
        $field->setFormTypeOptionIfNotSet('data_class', null);

        if ($field->getCustomOption(EntityField::OPTION_LINK_TO_ENTITY) === true) {
            $field->setCustomOption(
                EntityField::OPTION_RELATED_URL,
                $this->generateLinkToAssociatedEntity(
                    $entityMetadata->targetCrudControllerFqcn(),
                    $targetEntityDto,
                ),
            );
        }

        $field->setValue($targetEntityInstance);
        $field->setFormattedValue(
            $this->formatAsString(
                $targetEntityInstance,
                $entityMetadata,
                $field,
            ),
        );
    }

    private function configureToManyAssociation(FieldDto $field, EntityMetadata $entityMetadata, AdminContext $context): void
    {
        $crud = Type\instance_of(CrudDto::class)->coerce($context->getCrud());

        $field->setSortable(false);
        $field->setFormTypeOptionIfNotSet('multiple', true);

        $sourceEntityId = $entityMetadata->sourceEntityId();

        $targetEntityRepository = Type\instance_of(EntityRepository::class)->coerce($this->entityManager->getRepository($entityMetadata->targetEntityFqcn()));
        $criteria               = [$field->getProperty() => $sourceEntityId];

        $isDetailAction = $crud->getCurrentAction() === Action::DETAIL;
        $renderType     = Type\string()->coerce($field->getCustomOption(EntityField::OPTION_RENDER_TYPE));
        if ($isDetailAction && $renderType === EntityField::OPTION_RENDER_TYPE_LIST) {
            $targetSingleIdentifierFieldName = $this->entityManager->getClassMetadata($entityMetadata->targetEntityFqcn())->getSingleIdentifierFieldName();
            $formattedValue                  = Vec\map(
                Type\vec(Type\instance_of($entityMetadata->targetEntityFqcn()))->coerce($targetEntityRepository->findBy($criteria)),
                function (object $entity) use ($entityMetadata, $field, $targetSingleIdentifierFieldName): array {
                    $relatedUrl = null;
                    if ($field->getCustomOption(EntityField::OPTION_LINK_TO_ENTITY) === true) {
                        $relatedUrl = $this->generateLinkToAssociatedEntity(
                            $entityMetadata->targetCrudControllerFqcn(),
                            $this->entityFactory->create(
                                $entityMetadata->targetEntityFqcn(),
                                $this->propertyAccessor->getValue($entity, $targetSingleIdentifierFieldName),
                            ),
                        );
                    }

                    return [
                        'relatedUrl' => $relatedUrl,
                        'formattedValue' => $this->formatAsString($entity, $entityMetadata, $field),
                    ];
                },
            );
        } else {
            $formattedValue = $sourceEntityId !== null ? $targetEntityRepository->count($criteria) : 0;
        }

        $field->setFormattedValue($formattedValue);
    }

    private function formatAsString(object|null $entityInstance, EntityMetadata $entityMetadata, FieldDto $field): string|null
    {
        if ($entityInstance === null) {
            return null;
        }

        $targetEntityDisplayField = $entityMetadata->targetEntityDisplayField();
        if ($targetEntityDisplayField !== null) {
            if (is_callable($targetEntityDisplayField)) {
                return $targetEntityDisplayField($entityInstance);
            }

            return (string) $this->propertyAccessor->getValue($entityInstance, $targetEntityDisplayField);
        }

        if ($entityInstance instanceof Stringable) {
            return (string) $entityInstance;
        }

        throw new RuntimeException(
            Str\format(
                'The "%s" field cannot be configured because it doesn\'t define the related entity display value set with the "setEntityDisplayField()" method. or implement "__toString()".',
                $field->getProperty(),
            ),
        );
    }

    private function generateLinkToAssociatedEntity(string $crudController, EntityDto $entityDto): string
    {
        return $this->adminUrlGenerator
            ->setController($crudController)
            ->setAction(Action::DETAIL)
            ->setEntityId($entityDto->getPrimaryKeyValue())
            ->unset(EA::MENU_INDEX)
            ->unset(EA::SUBMENU_INDEX)
            ->includeReferrer()
            ->generateUrl();
    }

    private function configureOnChange(FieldDto $field, EntityMetadata $entityMetadata): void
    {
        $onChangeCallable = $field->getCustomOption(EntityField::OPTION_ON_CHANGE);

        if ($onChangeCallable === null) {
            return;
        }

        invariant(
            is_callable($onChangeCallable),
            Str\format('The "%s" field cannot be configured because the onChange option is not null or callable.', $field->getProperty()),
        );

        $field->setFormTypeOption('attr.data-' . EntityField::PARAM_ON_CHANGE_CONTEXT_FIELD_PROPERTY, $field->getProperty());

        $formOnChangeUrlCallable = $field->getCustomOption(EntityField::OPTION_ON_CHANGE_URL_CALLABLE);

        if ($formOnChangeUrlCallable !== null) {
            invariant(
                is_callable($formOnChangeUrlCallable),
                Str\format('Form onChange url callable option is not null or callable.'),
            );
            $controllerUrl = Type\string()->coerce($formOnChangeUrlCallable($entityMetadata));
        } else {
            $controllerUrl = $this->adminUrlGenerator
                ->unsetAll()
                ->setController($entityMetadata->sourceCrudControllerFqcn())
                ->setAction('formOnChange')
                ->generateUrl();
        }

        $field->setFormTypeOption('attr.data-' . EntityField::PARAM_ON_CHANGE_CONTEXT_HANDLE_URL, $controllerUrl);
    }

    /**
     * @return string|(callable(object):?string)|null
     */
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
