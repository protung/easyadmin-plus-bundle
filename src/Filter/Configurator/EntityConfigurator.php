<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Filter\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Override;
use Protung\EasyAdminPlusBundle\Field\EntityField;
use Protung\EasyAdminPlusBundle\Form\Type\CrudAutocompleteType;
use Protung\EasyAdminPlusBundle\Router\AutocompleteActionAdminUrlGenerator;
use Psl\Str;
use Psl\Type;
use RuntimeException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see https://github.com/EasyCorp/EasyAdminBundle/issues/4244
 */
final readonly class EntityConfigurator implements FilterConfiguratorInterface
{
    public function __construct(
        private AutocompleteActionAdminUrlGenerator $autocompleteActionAdminUrlGenerator,
        private TranslatorInterface $translator,
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

        $targetCrudControllerFqcn = Type\nullable(Type\class_string(CrudControllerInterface::class))
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

        if (! $entityDto->getClassMetadata()->hasAssociation($propertyName)) {
            $filterDto->setFormTypeOption('value_type_options.class', $targetEntityFqcn);
        }

        $filterDto->setFormTypeOption(
            'value_type_options.choice_label',
            fn (object $targetEntityInstance): string|null => EntityField::formatAsString($targetEntityInstance, $fieldDto, $this->translator),
        );

        $autocompleteMode = Type\bool()->coerce($fieldDto->getCustomOption(EntityField::OPTION_AUTOCOMPLETE));
        $widgetMode       = Type\string()->coerce($fieldDto->getCustomOption(EntityField::OPTION_WIDGET));
        if ($widgetMode === EntityField::WIDGET_AUTOCOMPLETE) {
            $filterDto->setFormTypeOption('value_type_options.attr.data-ea-widget', 'ea-autocomplete');
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

        $autocompleteEndpointUrl = $this->autocompleteActionAdminUrlGenerator
            ->generate(
                $context,
                $targetCrudControllerFqcn,
                $propertyName,
                Crud::PAGE_INDEX, // Filter is used from an index page.
                $fieldDto->getCustomOption(EntityField::OPTION_ENTITY_DISPLAY_FIELD) !== null,
            );
        $filterDto->setFormTypeOption('value_type_options.attr.data-ea-autocomplete-endpoint-url', $autocompleteEndpointUrl);
    }
}
