<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Field\Configurator\EntityConfigurator;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;

/**
 * @psalm-immutable
 */
final class EntityMetadata
{
    private object|int|string|null $sourceEntityId;

    private string $sourceCrudControllerFqcn;

    /** @var class-string<CrudControllerInterface> */
    private string $targetCrudControllerFqcn;

    /** @var class-string */
    private string $targetEntityFqcn;

    /** @var string|(callable(object):?string)|null */
    private $targetEntityDisplayField;

    private object|int|string|null $targetEntityId;

    /**
     * @param class-string<CrudControllerInterface>  $targetCrudControllerFqcn
     * @param class-string                           $targetEntityFqcn
     * @param string|(callable(object):?string)|null $targetEntityDisplayField
     */
    public function __construct(
        object|int|string|null $sourceEntityId,
        string $sourceCrudControllerFqcn,
        string $targetCrudControllerFqcn,
        string $targetEntityFqcn,
        string|callable|null $targetEntityDisplayField,
        object|int|string|null $targetEntityId,
    ) {
        $this->sourceEntityId           = $sourceEntityId;
        $this->sourceCrudControllerFqcn = $sourceCrudControllerFqcn;
        $this->targetCrudControllerFqcn = $targetCrudControllerFqcn;
        $this->targetEntityFqcn         = $targetEntityFqcn;
        $this->targetEntityDisplayField = $targetEntityDisplayField;
        $this->targetEntityId           = $targetEntityId;
    }

    public function sourceEntityId(): object|int|string|null
    {
        return $this->sourceEntityId;
    }

    public function sourceCrudControllerFqcn(): string
    {
        return $this->sourceCrudControllerFqcn;
    }

    /**
     * @return class-string<CrudControllerInterface>
     */
    public function targetCrudControllerFqcn(): string
    {
        return $this->targetCrudControllerFqcn;
    }

    /**
     * @return class-string
     */
    public function targetEntityFqcn(): string
    {
        return $this->targetEntityFqcn;
    }

    /**
     * @return string|(callable(object):?string)|null
     */
    public function targetEntityDisplayField(): string|callable|null
    {
        return $this->targetEntityDisplayField;
    }

    public function targetEntityId(): object|int|string|null
    {
        return $this->targetEntityId;
    }
}
