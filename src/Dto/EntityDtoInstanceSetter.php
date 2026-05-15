<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Dto;

use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use ReflectionClass;

final readonly class EntityDtoInstanceSetter
{
    public static function setInstance(EntityDto $entityDto, object|null $instance): void
    {
        $entityDtoReflection = new ReflectionClass($entityDto);
        if ($entityDtoReflection->hasProperty('entityInstance')) { // EasyAdmin 5+
            $instanceProperty = $entityDtoReflection->getProperty('entityInstance');
        } else {
            $instanceProperty = $entityDtoReflection->getProperty('instance');
        }

        $instanceProperty->setValue($entityDto, $instance);

        $primaryKeyProperty = $entityDtoReflection->getProperty('primaryKeyValue');
        $primaryKeyProperty->setValue($entityDto, null);
    }
}
