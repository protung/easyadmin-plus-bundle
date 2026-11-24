<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity;

use Doctrine\ORM\Mapping as ORM;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Enum\TaskStatus;

#[ORM\Entity]
class Task
{
    #[ORM\Id]
    #[ORM\Column]
    public int $id;

    #[ORM\Column(nullable: true, enumType: TaskStatus::class)]
    public TaskStatus|null $status = null;
}
