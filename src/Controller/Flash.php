<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Controller;

enum Flash: string
{
    case Success = 'success';

    case Warning = 'warning';

    case Error = 'danger';

    case Info = 'info';
}
