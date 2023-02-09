<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle;

use Protung\EasyAdminPlusBundle\DependencyInjection\CompilerPass\EasyAdminMenuItemMatcher;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class ProtungEasyAdminPlusBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new EasyAdminMenuItemMatcher());
    }
}
