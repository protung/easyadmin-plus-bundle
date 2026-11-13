<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Dto;

final class NoteDto
{
    /**
     * EasyAdmin reads the primary key of the instance it shows, which is the DTO.
     */
    public int|null $id = null;

    public string|null $title = null;
}
