<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Enum;

enum TaskStatus: string
{
    case Open = 'is-open';
    case Done = 'is-done';
}
