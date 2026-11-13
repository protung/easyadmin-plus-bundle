<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Override;
use Protung\EasyAdminPlusBundle\Controller\BaseCrudDtoController;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Dto\NoteDto;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Note;

/**
 * Notes created and edited through a DTO.
 *
 * @template-extends BaseCrudDtoController<Note, NoteDto>
 */
final class NoteCrudController extends BaseCrudDtoController
{
    #[Override]
    public static function getEntityFqcn(): string
    {
        return Note::class;
    }

    #[Override]
    public static function getDtoFqcn(): string
    {
        return NoteDto::class;
    }

    /** @return iterable<TextField> */
    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('title');
    }

    #[Override]
    public function createEntityFromDto(object $dto): Note
    {
        $note        = new Note();
        $note->title = $dto->title ?? '';

        return $note;
    }

    #[Override]
    public function createDtoFromEntity(object $entity): NoteDto
    {
        $dto        = new NoteDto();
        $dto->id    = $entity->id;
        $dto->title = $entity->title;

        return $dto;
    }

    #[Override]
    public function updateEntityFromDto(object $entity, object $dto): void
    {
        $entity->title = $dto->title ?? '';
    }
}
