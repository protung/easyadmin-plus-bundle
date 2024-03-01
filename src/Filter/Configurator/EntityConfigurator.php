<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Filter\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Override;
use Protung\EasyAdminPlusBundle\Field\EntityField;
use Protung\EasyAdminPlusBundle\Form\Type\CrudAutocompleteType;
use Psl\Str;
use Psl\Type;
use RuntimeException;
use Stringable;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

use function is_callable;

/**
 * @see https://github.com/EasyCorp/EasyAdminBundle/issues/4244
 */
final readonly class EntityConfigurator implements FilterConfiguratorInterface
{
    public function __construct(
        private AdminUrlGeneratorInterface $adminUrlGenerator,
        private PropertyAccessorInterface $propertyAccessor,
    ) {
    }

    #[Override]
    public function supports(FilterDto $filterDto, FieldDto|null $fieldDto, EntityDto $entityDto, AdminContext $context): bool
    {
        return $filterDto->getFqcn() === EntityFilter::class && $fieldDto?->getFieldFqcn() === EntityField::class;
    }

    #[Override]
    public function configure(FilterDto $filterDto, FieldDto|null $fieldDto, EntityDto $entityDto, AdminContext $context): void
    {
        if ($fieldDto === null) {
            return;
        }

        $propertyName = $filterDto->getProperty();

        $targetCrudControllerFqcn = Type\nullable(Type\string())
            ->coerce($fieldDto->getCustomOption(EntityField::OPTION_CRUD_CONTROLLER));
        if ($targetCrudControllerFqcn === null) {
            throw new RuntimeException(
                Str\format(
                    'The "%s" filter cannot be configured because it does not define the related CRUD controller FQCN with the "setCrudController()" method.',
                    $propertyName,
                ),
            );
        }

        $targetEntityFqcn = Type\string()->coerce($context->getCrudControllers()->findEntityFqcnByCrudFqcn($targetCrudControllerFqcn));

        if (! $entityDto->isAssociation($propertyName)) {
            $filterDto->setFormTypeOption('value_type_options.class', $targetEntityFqcn);
        }

        $filterDto->setFormTypeOption(
            'value_type_options.choice_label',
            fn (object $targetEntityInstance): string|null => $this->formatAsString($targetEntityInstance, $fieldDto),
        );

        $autocompleteMode = Type\bool()->coerce($fieldDto->getCustomOption(EntityField::OPTION_AUTOCOMPLETE));
        $widgetMode       = Type\string()->coerce($fieldDto->getCustomOption(EntityField::OPTION_WIDGET));
        if ($widgetMode === EntityField::WIDGET_AUTOCOMPLETE) {
            $filterDto->setFormTypeOption('value_type_options.attr.data-ea-widget', 'ea-autocomplete');
        } elseif ($widgetMode === EntityField::WIDGET_NATIVE) {
            $filterDto->setFormTypeOption('value_type_options.class', $targetEntityFqcn);
        }

        if ($autocompleteMode !== true) {
            return;
        }

        if ($widgetMode !== EntityField::WIDGET_AUTOCOMPLETE) {
            throw new RuntimeException(
                Str\format(
                    'The "%s" field cannot be configured with autocomplete mode for not autocomplete widget .',
                    $fieldDto->getProperty(),
                ),
            );
        }

        $filterDto->setFormTypeOptionIfNotSet('value_type', CrudAutocompleteType::class);

        $autocompleteEndpointUrl = $this->adminUrlGenerator
            ->set('page', 1) // The autocomplete should always start on the first page
            ->setController($targetCrudControllerFqcn)
            ->setAction('autocomplete')
            ->setEntityId(null)
            ->unset(EA::SORT) // Avoid passing the 'sort' param from the current entity to the autocompleted one
            ->set(
                EntityField::PARAM_AUTOCOMPLETE_CONTEXT,
                [
                    EA::CRUD_CONTROLLER_FQCN => $context->getRequest()->query->get(EA::CRUD_CONTROLLER_FQCN),
                    'propertyName' => $propertyName,
                    'originatingPage' => Crud::PAGE_INDEX,
                    EntityField::OPTION_ENTITY_DISPLAY_FIELD => $fieldDto->getCustomOption(EntityField::OPTION_ENTITY_DISPLAY_FIELD) !== null,
                ],
            )
            ->generateUrl();

        $filterDto->setFormTypeOption('value_type_options.attr.data-ea-autocomplete-endpoint-url', $autocompleteEndpointUrl);
    }

    private function formatAsString(object|null $entityInstance, FieldDto $field): string|null
    {
        if ($entityInstance === null) {
            return null;
        }

        $targetEntityDisplayField = EntityField::getEntityDisplayField($field);
        if ($targetEntityDisplayField !== null) {
            if (is_callable($targetEntityDisplayField)) {
                return $targetEntityDisplayField($entityInstance);
            }

            return Type\nullable(Type\string())->coerce($this->propertyAccessor->getValue($entityInstance, $targetEntityDisplayField));
        }

        if ($entityInstance instanceof Stringable) {
            return (string) $entityInstance;
        }

        throw new RuntimeException(
            Str\format(
                'The "%s" field cannot be configured because it does not define the related entity display value set with the "setEntityDisplayField()" method. or implement "__toString()".',
                $field->getProperty(),
            ),
        );
    }
}
