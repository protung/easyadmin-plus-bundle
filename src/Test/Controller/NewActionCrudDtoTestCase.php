<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use Protung\EasyAdminPlusBundle\Controller\BaseCrudDtoController;

/**
 * @template TEntity of object
 * @template TDto of object
 * @template TController of BaseCrudDtoController<TEntity, TDto>
 * @template-extends NewActionTestCase<TEntity, TController>
 */
abstract class NewActionCrudDtoTestCase extends NewActionTestCase
{
}
