<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Field;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Factory\EntityFactory;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Override;
use Protung\EasyAdminPlusBundle\Field\Configurator\EntityConfigurator\EntityMetadata;
use Protung\EasyAdminPlusBundle\Form\Type\EntityFieldDoctrineType;
use Symfony\Contracts\Translation\TranslatableInterface;

final class EntityField implements FieldInterface
{
    use FieldTrait;
    use AdvancedDisplayField;
    use CallbackConfigurableField;

    public const string OPTION_CRUD_CONTROLLER = AssociationField::OPTION_EMBEDDED_CRUD_FORM_CONTROLLER;

    public const string OPTION_AUTOCOMPLETE = AssociationField::OPTION_AUTOCOMPLETE;

    public const string OPTION_WIDGET = AssociationField::OPTION_WIDGET;

    public const string WIDGET_AUTOCOMPLETE = AssociationField::WIDGET_AUTOCOMPLETE;

    public const string WIDGET_NATIVE = AssociationField::WIDGET_NATIVE;

    public const string OPTION_TURBO_DRIVE_ENABLED = 'turboDriveEnabled';

    public const string OPTION_RENDER_TYPE = 'renderType';

    public const string OPTION_RENDER_TYPE_LIST = 'asList';

    public const string OPTION_RENDER_TYPE_COUNT = 'asCount';

    public const string OPTION_QUERY_BUILDER_CALLABLE = AssociationField::OPTION_QUERY_BUILDER_CALLABLE;

    public const string OPTION_ENTITY_DTO_FACTORY_CALLABLE = 'entityDtoFactoryCallable';

    public const string OPTION_LINK_TO_ENTITY = 'linkToEntity';

    /** @internal this option is intended for internal use only */
    public const string OPTION_RELATED_URL = AssociationField::OPTION_RELATED_URL;

    /** @internal this option is intended for internal use only */
    public const string OPTION_DOCTRINE_ASSOCIATION_TYPE = AssociationField::OPTION_DOCTRINE_ASSOCIATION_TYPE;

    public const string DOCTRINE_ASSOCIATION_TYPE_SINGLE = 'toOne';

    public const string DOCTRINE_ASSOCIATION_TYPE_MANY = 'toMany';

    /** @internal this option is intended for internal use only */
    public const string PARAM_AUTOCOMPLETE_CONTEXT = AssociationField::PARAM_AUTOCOMPLETE_CONTEXT;

    public const string OPTION_ON_CHANGE = 'onChange';

    public const string OPTION_ON_CHANGE_URL_CALLABLE = 'onChangeUrlCallable';

    public const string PARAM_ON_CHANGE_CONTEXT_FIELD_PROPERTY = 'ea-custom-entity-field-on-change-field-property';

    public const string PARAM_ON_CHANGE_CONTEXT_HANDLE_URL = 'ea-custom-entity-field-on-change-handle-url';

    #[Override]
    public static function new(string $propertyName, TranslatableInterface|string|false|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplatePath('@ProtungEasyAdminPlus/crud/field/entity.html.twig')
            ->setFormType(EntityFieldDoctrineType::class)
            ->addCssClass('field-association')
            ->setCustomOption(self::OPTION_CRUD_CONTROLLER, null)
            ->setCustomOption(self::OPTION_TURBO_DRIVE_ENABLED, 'false')
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

    public function enableTurboDrive(): self
    {
        $this->setCustomOption(self::OPTION_TURBO_DRIVE_ENABLED, 'true');

        return $this;
    }

    public function disableTurboDrive(): self
    {
        $this->setCustomOption(self::OPTION_TURBO_DRIVE_ENABLED, 'false');

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

    public function operateOnIds(): self
    {
        $this->setFormTypeOption('is_association', false);

        return $this;
    }

    public function operateOnEntities(): self
    {
        $this->setFormTypeOption('is_association', true);

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

    /**
     * @param (bool|(callable(AdminContext): bool)) $callable
     */
    public function linkToEntity(bool|callable $link = true): self
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
     * @param (callable(QueryBuilder): void) $callable
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

    public function readonly(bool $readonly = true): self
    {
        $this->setDisabled($readonly);

        // For readonly fields it is useful to not load the choices to improve performance for fields that might have many options.
        $this->setFormTypeOptionIfNotSet('choice_lazy', $readonly);

        return $this;
    }
}
