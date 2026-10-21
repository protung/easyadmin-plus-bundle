<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Report
{
    #[ORM\Id]
    #[ORM\Column]
    public int $id;
}
