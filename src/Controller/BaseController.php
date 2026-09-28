<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

abstract class BaseController extends AbstractController
{
    use FlashMessages;
}
