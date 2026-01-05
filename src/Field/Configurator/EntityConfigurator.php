<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Field\Configurator;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\DashboardControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\CrudDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Factory\EntityFactory;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Override;
use Protung\EasyAdminPlusBundle\Field\Configurator\EntityConfigurator\EntityMetadata;
use Protung\EasyAdminPlusBundle\Field\EntityField;
use Protung\EasyAdminPlusBundle\Form\Type\CrudAutocompleteType;
use Protung\EasyAdminPlusBundle\Router\AutocompleteActionAdminUrlGenerator;
use Psl\Class;
use Psl\Str;
use Psl\Type;
use Psl\Vec;
use RuntimeException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function is_bool;
use function is_callable;
use function Psl\invariant;

final readonly class EntityConfigurator implements FieldConfiguratorInterface
{
    public function __construct(
        private EntityFactory $entityFactory,
        private AdminUrlGenerator $adminUrlGenerator,
        private AutocompleteActionAdminUrlGenerator $autocompleteActionAdminUrlGenerator,
        private TranslatorInterface $translator,
        private PropertyAccessorInterface $propertyAccessor,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return $field->getFieldFqcn() === EntityField::class;
    }

    #[Override]
    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $propertyName = $field->getProperty();

        $targetCrudControllerFqcn = Type\nullable(Type\class_string(CrudControllerInterface::class))->coerce($field->getCustomOption(EntityField::OPTION_CRUD_CONTROLLER));
        if ($targetCrudControllerFqcn === null) {
            throw new RuntimeException(
                Str\format(
                    'The "%s" field cannot be configured because it doesn\'t define the related CRUD controller FQCN with the "setCrudController()" method.',
                    $field->getProperty(),
                ),
            );
        }

        $crud = Type\instance_of(CrudDto::class)->coerce($context->getCrud());

        $sourceCrudControllerFqcn = Type\class_string(CrudControllerInterface::class)->coerce($crud->getControllerFqcn());

        $targetEntityFqcn = Type\string()->coerce($context->getCrudControllers()->findEntityFqcnByCrudFqcn($targetCrudControllerFqcn));
        invariant(Class\exists($targetEntityFqcn), 'Could not determine target entity for set CRUD controller.');
        invariant(Class\exists($targetCrudControllerFqcn), 'Could not determine target CRUD controller.');

        $entityMetadata = new EntityMetadata(
            $entityDto->getPrimaryKeyValue(),
            $sourceCrudControllerFqcn,
            Type\class_string(DashboardControllerInterface::class)->coerce($context->getDashboardControllerFqcn()),
            $targetCrudControllerFqcn,
            $targetEntityFqcn,
            $field->getValue(),
        );

        $field->setFormTypeOptionIfNotSet('is_association', $entityDto->getClassMetadata()->hasAssociation($propertyName));
        $this->configureOnChange($field, $entityMetadata);

        $associationType = Type\string()->coerce($field->getCustomOption(EntityField::OPTION_DOCTRINE_ASSOCIATION_TYPE));
        if ($associationType === EntityField::DOCTRINE_ASSOCIATION_TYPE_SINGLE) {
            $this->configureToOneAssociation($field, $entityMetadata, $context);
        } elseif ($associationType === EntityField::DOCTRINE_ASSOCIATION_TYPE_MANY) {
            $this->configureToManyAssociation($field, $entityMetadata, $context);
        } else {
            throw new RuntimeException('Unknown association type.');
        }

        $field->setFormTypeOption(
            'choice_label',
            fn (object $targetEntityInstance): string|null => EntityField::formatAsString($targetEntityInstance, $field, $this->translator),
        );

        $autocompleteMode = Type\bool()->coerce($field->getCustomOption(EntityField::OPTION_AUTOCOMPLETE));
        $widgetMode       = Type\string()->coerce($field->getCustomOption(EntityField::OPTION_WIDGET));
        if ($widgetMode === EntityField::WIDGET_AUTOCOMPLETE) {
            $field->setFormTypeOption('attr.data-ea-widget', 'ea-autocomplete');
        } elseif ($widgetMode === EntityField::WIDGET_NATIVE) {
            $field->setFormTypeOption('class', $entityMetadata->targetEntityFqcn());
        }

        if ($autocompleteMode === true && $field->getFormTypeOption('disabled') !== true) {
            if ($widgetMode !== EntityField::WIDGET_AUTOCOMPLETE) {
                throw new RuntimeException(
                    Str\format(
                        'The "%s" field cannot be configured with autocomplete mode for not autocomplete widget .',
                        $field->getProperty(),
                    ),
                );
            }

            $field->setFormType(CrudAutocompleteType::class);
            $autocompleteEndpointUrl = $this->autocompleteActionAdminUrlGenerator
                ->generate(
                    $context,
                    $entityMetadata->targetCrudControllerFqcn(),
                    $propertyName,
                    $crud->getCurrentPage() ?? Crud::PAGE_INDEX,
                    $field->getCustomOption(EntityField::OPTION_ENTITY_DISPLAY_FIELD) !== null,
                );

            $field->setFormTypeOption('attr.data-ea-autocomplete-endpoint-url', $autocompleteEndpointUrl);
        } else {
            $field->setFormTypeOptionIfNotSet('query_builder', static function (EntityRepository $repository) use ($field): QueryBuilder {
                // it would then be identical to the one used in autocomplete action, but it is a bit complex getting it in here
                $queryBuilder         = $repository->createQueryBuilder('entity');
                $queryBuilderCallable = $field->getCustomOption(EntityField::OPTION_QUERY_BUILDER_CALLABLE);
                if ($queryBuilderCallable !== null) {
                    invariant(
                        is_callable($queryBuilderCallable),
                        Str\format('Query builder callable option is not null or callable.'),
                    );
                    $queryBuilderCallable($queryBuilder);
                }

                return $queryBuilder;
            });
        }
    }

    private function configureToOneAssociation(FieldDto $field, EntityMetadata $entityMetadata, AdminContext $context): void
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

        if ($this->resolveLinkToEntityOption($field, $context)) {
            $field->setCustomOption(
                EntityField::OPTION_RELATED_URL,
                $this->generateLinkToAssociatedEntity(
                    $entityMetadata,
                    $targetEntityDto,
                ),
            );
        }

        $field->setValue($targetEntityInstance);
        $field->setFormattedValue(
            EntityField::formatAsString($targetEntityInstance, $field, $this->translator),
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
                function (object $entity) use ($entityMetadata, $field, $targetSingleIdentifierFieldName, $context): array {
                    $relatedUrl = null;
                    if ($this->resolveLinkToEntityOption($field, $context)) {
                        $relatedUrl = $this->generateLinkToAssociatedEntity(
                            $entityMetadata,
                            $this->entityFactory->create(
                                $entityMetadata->targetEntityFqcn(),
                                $this->propertyAccessor->getValue($entity, $targetSingleIdentifierFieldName),
                            ),
                        );
                    }

                    return [
                        'relatedUrl' => $relatedUrl,
                        'formattedValue' => EntityField::formatAsString($entity, $field, $this->translator),
                    ];
                },
            );
        } else {
            $formattedValue = $sourceEntityId !== null ? $targetEntityRepository->count($criteria) : 0;
        }

        $field->setFormattedValue($formattedValue);
    }

    private function generateLinkToAssociatedEntity(EntityMetadata $entityMetadata, EntityDto $entityDto): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setDashboard($entityMetadata->targetDashboardControllerFqcn())
            ->setController($entityMetadata->targetCrudControllerFqcn())
            ->setAction(Action::DETAIL)
            ->setEntityId($entityDto->getPrimaryKeyValue())
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

    private function resolveLinkToEntityOption(FieldDto $field, AdminContext $context): bool
    {
        $optionValue = $field->getCustomOption(EntityField::OPTION_LINK_TO_ENTITY);

        if (is_bool($optionValue)) {
            return $optionValue;
        }

        invariant(
            is_callable($optionValue),
            Str\format('The "%s" field cannot be configured because the linkToEntity option is not boolean or callable.', $field->getProperty()),
        );

        return Type\bool()->coerce($optionValue($context));
    }
}
