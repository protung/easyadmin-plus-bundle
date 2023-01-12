<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\CrudDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use Psl\Class;
use Psl\Type;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormInterface;

/**
 * Original inspiration from https://gist.github.com/ragboyjr/2ed5734eb839483ca22892f6955b2792
 *
 * @template TEntityClass of object
 * @template TDtoClass of object
 */
abstract class BaseCrudDtoController extends BaseCrudController implements EventSubscriberInterface
{
    /** @var TEntityClass|null */
    private object|null $temporaryEntityForEdit = null;

    /**
     * @return class-string<TEntityClass>
     */
    abstract public static function getEntityFqcn(): string;

    /**
     * @return class-string<TDtoClass>
     */
    abstract public static function getDtoFqcn(): string;

    /**
     * @param TDtoClass $dto
     *
     * @return TEntityClass|null
     */
    abstract public function createEntityFromDto(object $dto): object|null;

    /**
     * @param TEntityClass $entity
     *
     * @return TDtoClass
     */
    abstract public function createDtoFromEntity(object $entity): object;

    /**
     * @param TEntityClass $entity
     * @param TDtoClass    $dto
     */
    abstract public function updateEntityFromDto(object $entity, object $dto): void;

    public function createNewForm(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormInterface
    {
        $form = parent::createNewForm($entityDto, $formOptions, $context);

        $this->removeEntityInstanceFromAdminContext($context);

        return $form;
    }

    final public function convertDtoToEntityFromBeforeEntityPersistedEvent(BeforeEntityPersistedEvent $event): void
    {
        if (! $this->matchesCrudController()) {
            return;
        }

        $dto = $event->getEntityInstance();
        if (! Type\instance_of(static::getDtoFqcn())->matches($dto)) {
            return;
        }

        $entity = $this->createEntityFromDto($dto);

        /**
         * @param TEntityClass|null $entity
         */
        $closure = function (object|null $entity): void {
            $this->entityInstance = $entity;
        };
        $closure->call($event, $entity);
    }

    final public function convertEntityToDtoFromBeforeCrudActionEvent(BeforeCrudActionEvent $event): void
    {
        $adminContext = Type\instance_of(AdminContext::class)->coerce($event->getAdminContext());
        $crud         = Type\instance_of(CrudDto::class)->coerce($adminContext->getCrud());

        if (! $this->matchesCrudController()) {
            return;
        }

        if ($crud->getCurrentAction() !== Action::EDIT) {
            return;
        }

        $entityDto = $adminContext->getEntity();

        $instance = $entityDto->getInstance();

        if (! Type\instance_of(static::getEntityFqcn())->matches($instance)) {
            return;
        }

        // hack: this triggers the primary key value to be cached on the entity DTO.
        // This is required because we won't have access to the actual entity with it's pkey from the entityDto.
        $entityDto->getPrimaryKeyValue();

        $this->temporaryEntityForEdit = $instance;
        $dto                          = $this->createDtoFromEntity($this->temporaryEntityForEdit);

        /**
         * @param TDtoClass $dto
         */
        $closure = function (object $dto): void {
            $this->instance = $dto;
        };
        $closure->call($entityDto, $dto);
    }

    final public function convertDtoToUpdatedEntityFromBeforeEntityUpdatedEvent(BeforeEntityUpdatedEvent $event): void
    {
        if (! $this->matchesCrudController()) {
            return;
        }

        $dto = $event->getEntityInstance();
        if (! Type\instance_of(static::getDtoFqcn())->matches($dto)) {
            return;
        }

        if ($this->temporaryEntityForEdit === null) {
            throw new RuntimeException('Temporary entity for edit variable was not set, something went wrong with the edit process.');
        }

        $this->updateEntityFromDto($this->temporaryEntityForEdit, $dto);

        /**
         * @param TEntityClass $entity
         */
        $closure = function (object $entity): void {
            $this->entityInstance = $entity;
        };
        $closure->call($event, $this->temporaryEntityForEdit);
    }

    /**
     * The entityId query param will get left in the generated urls when you click around the admin.
     * For example, if you visit show, and then hit the Back To Listings button, the url will have the recently shown entityId still in the url.
     * When creating a new entity, it causes the admin context provider to try and load the old entity by id and set it on the EntityDto.
     * This then causes an exception when we later try to setInstance on the EntityDto to a Dto class and not the Entity.
     */
    final public function removeEntityFromContextOnBeforeCrudActionEvent(BeforeCrudActionEvent $event): void
    {
        if (! $this->matchesCrudController()) {
            return;
        }

        $adminContext = Type\instance_of(AdminContext::class)->coerce($event->getAdminContext());
        $crud         = Type\instance_of(CrudDto::class)->coerce($adminContext->getCrud());

        if ($crud->getCurrentAction() !== Action::NEW) {
            return;
        }

        $this->removeEntityInstanceFromAdminContext($adminContext);
    }

    private function removeEntityInstanceFromAdminContext(AdminContext $adminContext): void
    {
        $entity = $adminContext->getEntity();

        $closure = function (): void {
            $this->instance = null;
        };
        $closure->call($entity);
    }

    /**
     * @return TDtoClass
     */
    public function createEntity(string $entityFqcn): object
    {
        $className = static::getDtoFqcn();

        return new $className();
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityPersistedEvent::class => [
                ['convertDtoToEntityFromBeforeEntityPersistedEvent'],
            ],
            BeforeCrudActionEvent::class => [
                ['convertEntityToDtoFromBeforeCrudActionEvent'],
                ['removeEntityFromContextOnBeforeCrudActionEvent'],
            ],
            BeforeEntityUpdatedEvent::class => [
                ['convertDtoToUpdatedEntityFromBeforeEntityUpdatedEvent'],
            ],
        ];
    }

    /**
     * If multiple CRUD DTO controllers have the same EntityFQCN then we might need to skip handling the events.
     */
    private function matchesCrudController(): bool
    {
        $adminContext = $this->currentAdminContext();
        $crud         = Type\instance_of(CrudDto::class)->coerce($adminContext->getCrud());

        $controller = $crud->getControllerFqcn();
        if ($controller === null || ! Class\exists($controller)) {
            return false;
        }

        return Type\instance_of($controller)->matches($this);
    }
}
