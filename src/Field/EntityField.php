<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Field;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Factory\EntityFactory;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Protung\EasyAdminPlusBundle\Field\Configurator\EntityConfigurator\EntityMetadata;
use Protung\EasyAdminPlusBundle\Form\Type\EntityFieldDoctrineType;
use Psl\Type;

use function is_callable;

final class EntityField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_CRUD_CONTROLLER = AssociationField::OPTION_EMBEDDED_CRUD_FORM_CONTROLLER;

    public const OPTION_ENTITY_DISPLAY_FIELD = 'entityDisplayField';

    public const OPTION_AUTOCOMPLETE = AssociationField::OPTION_AUTOCOMPLETE;

    public const OPTION_WIDGET = AssociationField::OPTION_WIDGET;

    public const WIDGET_AUTOCOMPLETE = AssociationField::WIDGET_AUTOCOMPLETE;

    public const WIDGET_NATIVE = AssociationField::WIDGET_NATIVE;

    public const OPTION_RENDER_TYPE = 'renderType';

    public const OPTION_RENDER_TYPE_LIST = 'asList';

    public const OPTION_RENDER_TYPE_COUNT = 'asCount';

    public const OPTION_QUERY_BUILDER_CALLABLE = AssociationField::OPTION_QUERY_BUILDER_CALLABLE;

    public const OPTION_ENTITY_DTO_FACTORY_CALLABLE = 'entityDtoFactoryCallable';

    public const OPTION_LINK_TO_ENTITY = 'linkToEntity';

    /** @internal this option is intended for internal use only */
    public const OPTION_RELATED_URL = AssociationField::OPTION_RELATED_URL;

    /** @internal this option is intended for internal use only */
    public const OPTION_DOCTRINE_ASSOCIATION_TYPE = AssociationField::OPTION_DOCTRINE_ASSOCIATION_TYPE;

    public const DOCTRINE_ASSOCIATION_TYPE_SINGLE = 'toOne';

    public const DOCTRINE_ASSOCIATION_TYPE_MANY = 'toMany';

    /** @internal this option is intended for internal use only */
    public const PARAM_AUTOCOMPLETE_CONTEXT = AssociationField::PARAM_AUTOCOMPLETE_CONTEXT;

    public const OPTION_ON_CHANGE = 'onChange';

    public const OPTION_ON_CHANGE_URL_CALLABLE = 'onChangeUrlCallable';

    public const PARAM_ON_CHANGE_CONTEXT_FIELD_PROPERTY = 'ea-custom-entity-field-on-change-field-property';

    public const PARAM_ON_CHANGE_CONTEXT_HANDLE_URL = 'ea-custom-entity-field-on-change-handle-url';

    public static function new(string $propertyName, string|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplatePath('@ProtungEasyAdminPlus/crud/field/entity.html.twig')
            ->setFormType(EntityFieldDoctrineType::class)
            ->addCssClass('field-association')
            ->setCustomOption(self::OPTION_CRUD_CONTROLLER, null)
            ->setCustomOption(self::OPTION_WIDGET, self::WIDGET_AUTOCOMPLETE)
            ->setCustomOption(self::OPTION_QUERY_BUILDER_CALLABLE, null)
            ->setCustomOption(self::OPTION_ENTITY_DTO_FACTORY_CALLABLE, null)
            ->setCustomOption(self::OPTION_ON_CHANGE, null)
            ->autocomplete()
            ->renderAsList()
            ->linkToEntity()
            ->setRequired(true)
            ->setAssociationToOne()
            ->setSortable(false);
    }

    public function setCrudController(string $crudControllerFqcn): self
    {
        $this->setCustomOption(self::OPTION_CRUD_CONTROLLER, $crudControllerFqcn);

        return $this;
    }

    /**
     * @param string|(callable(mixed): string) $entityDisplayField
     */
    public function setEntityDisplayField(string|callable $entityDisplayField): self
    {
        $this->setCustomOption(self::OPTION_ENTITY_DISPLAY_FIELD, $entityDisplayField);

        return $this;
    }

    public function disabled(bool $disabled = true): self
    {
        $this->setFormTypeOption('disabled', $disabled);

        return $this;
    }

    public function setAssociationToOne(): self
    {
        $this->setCustomOption(self::OPTION_DOCTRINE_ASSOCIATION_TYPE, self::DOCTRINE_ASSOCIATION_TYPE_SINGLE);

        return $this;
    }

    public function setAssociationToMany(): self
    {
        $this->setCustomOption(self::OPTION_DOCTRINE_ASSOCIATION_TYPE, self::DOCTRINE_ASSOCIATION_TYPE_MANY);

        return $this;
    }

    public function autocomplete(bool $autocomplete = true): self
    {
        $this->setCustomOption(self::OPTION_AUTOCOMPLETE, $autocomplete);

        return $this;
    }

    public function renderAsNativeWidget(bool $asNative = true): self
    {
        $this->setCustomOption(self::OPTION_WIDGET, $asNative ? self::WIDGET_NATIVE : self::WIDGET_AUTOCOMPLETE);

        return $this;
    }

    public function renderAsList(bool $asList = true): self
    {
        $this->setCustomOption(self::OPTION_RENDER_TYPE, $asList ? self::OPTION_RENDER_TYPE_LIST : self::OPTION_RENDER_TYPE_COUNT);

        return $this;
    }

    public function linkToEntity(bool $link = true): self
    {
        $this->setCustomOption(self::OPTION_LINK_TO_ENTITY, $link);

        return $this;
    }

    public function onChange(callable $callable): self
    {
        $this->setCustomOption(self::OPTION_ON_CHANGE, $callable);

        return $this;
    }

    /**
     * @param (callable(EntityMetadata): string) $callable
     */
    public function setOnChangeUrlCallable(callable $callable): self
    {
        $this->setCustomOption(self::OPTION_ON_CHANGE_URL_CALLABLE, $callable);

        return $this;
    }

    /**
     * @param (callable(QueryBuilder, AdminContext): void) $callable
     */
    public function setQueryBuilderCallable(callable $callable): self
    {
        $this->setCustomOption(self::OPTION_QUERY_BUILDER_CALLABLE, $callable);

        return $this;
    }

    /**
     * @param (callable(EntityFactory, EntityMetadata): ?EntityDto) $callable
     */
    public function setEntityDtoFactoryCallable(callable $callable): self
    {
        $this->setCustomOption(self::OPTION_ENTITY_DTO_FACTORY_CALLABLE, $callable);

        return $this;
    }

    /**
     * @return string|(callable(object):?string)|null
     */
    public static function getEntityDisplayField(FieldDto $field): string|callable|null
    {
        /** @var string|(callable(object):?string)|null $value */
        $value = $field->getCustomOption(self::OPTION_ENTITY_DISPLAY_FIELD);

        if (is_callable($value)) {
            return $value;
        }

        return Type\nullable(Type\string())->coerce($value);
    }
}
