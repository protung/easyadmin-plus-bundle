<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Dto;

use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use ReflectionProperty;

final readonly class EntityDtoInstanceSetter
{
    public static function setInstance(EntityDto $entityDto, object|null $instance): void
    {
        $instanceProperty = new ReflectionProperty($entityDto, 'instance');
        $instanceProperty->setValue($entityDto, $instance);

        $primaryKeyProperty = new ReflectionProperty($entityDto, 'primaryKeyValue');
        $primaryKeyProperty->setValue($entityDto, null);
    }
}
