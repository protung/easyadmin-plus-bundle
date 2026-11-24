<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterCrudActionEvent;
use Override;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Dto\NoteDto;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Note;
use Psl\Type;

/**
 * Notes whose switch saves the entity itself, as EasyAdmin can't save a DTO.
 */
// @mago-expect lint:final-controller Extended by SaveForbiddenNoteCrudController.
class SwitchNoteCrudController extends NoteCrudController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Override]
    protected function ajaxEdit(EntityDto $entityDto, string|null $propertyName, bool $newValue): AfterCrudActionEvent
    {
        $dto  = Type\instance_of(NoteDto::class)->coerce($entityDto->getInstance());
        $note = Type\instance_of(Note::class)->coerce($this->entityManager->find(Note::class, $dto->id));

        $note->pinned = $newValue;
        $this->entityManager->flush();

        return new AfterCrudActionEvent(
            $this->getContext(),
            KeyValueStore::new(['action' => Action::EDIT, 'entity' => $entityDto]),
        );
    }
}
