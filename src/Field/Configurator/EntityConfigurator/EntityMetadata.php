<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Field\Configurator\EntityConfigurator;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\DashboardControllerInterface;

/**
 * @psalm-immutable
 */
final class EntityMetadata
{
    private object|int|string|null $sourceEntityId;

    /** @var class-string<CrudControllerInterface> */
    private string $sourceCrudControllerFqcn;

    /** @var class-string<DashboardControllerInterface> */
    private string $targetDashboardControllerFqcn;

    /** @var class-string<CrudControllerInterface> */
    private string $targetCrudControllerFqcn;

    /** @var class-string */
    private string $targetEntityFqcn;

    private object|int|string|null $targetEntityId;

    /**
     * @param class-string<CrudControllerInterface>      $sourceCrudControllerFqcn
     * @param class-string<DashboardControllerInterface> $targetDashboardControllerFqcn
     * @param class-string<CrudControllerInterface>      $targetCrudControllerFqcn
     * @param class-string                               $targetEntityFqcn
     */
    public function __construct(
        object|int|string|null $sourceEntityId,
        string $sourceCrudControllerFqcn,
        string $targetDashboardControllerFqcn,
        string $targetCrudControllerFqcn,
        string $targetEntityFqcn,
        object|int|string|null $targetEntityId,
    ) {
        $this->sourceEntityId                = $sourceEntityId;
        $this->sourceCrudControllerFqcn      = $sourceCrudControllerFqcn;
        $this->targetDashboardControllerFqcn = $targetDashboardControllerFqcn;
        $this->targetCrudControllerFqcn      = $targetCrudControllerFqcn;
        $this->targetEntityFqcn              = $targetEntityFqcn;
        $this->targetEntityId                = $targetEntityId;
    }

    public function sourceEntityId(): object|int|string|null
    {
        return $this->sourceEntityId;
    }

    /**
     * @return class-string<CrudControllerInterface>
     */
    public function sourceCrudControllerFqcn(): string
    {
        return $this->sourceCrudControllerFqcn;
    }

    /**
     * @return class-string<DashboardControllerInterface>
     */
    public function targetDashboardControllerFqcn(): string
    {
        return $this->targetDashboardControllerFqcn;
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

    public function targetEntityId(): object|int|string|null
    {
        return $this->targetEntityId;
    }
}
