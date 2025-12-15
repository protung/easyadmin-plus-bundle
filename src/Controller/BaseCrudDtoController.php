<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Controller;

use BadMethodCallException;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use EasyCorp\Bundle\EasyAdminBundle\Exception\InsufficientEntityPermissionException;
use EasyCorp\Bundle\EasyAdminBundle\Factory\ActionFactory;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FieldFactory;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Security\Permission;
use Override;
use Psl\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;

use function class_exists;

/**
 * Original inspiration from https://gist.github.com/ragboyjr/2ed5734eb839483ca22892f6955b2792
 *
 * @template TEntity of object
 * @template TDto of object
 * @template-extends BaseCrudController<TEntity>
 */
abstract class BaseCrudDtoController extends BaseCrudController
{
    /**
     * @return class-string<TDto>
     */
    abstract public static function getDtoFqcn(): string;

    /**
     * @param TDto $dto
     *
     * @return TEntity|null
     */
    abstract public function createEntityFromDto(object $dto): object|null;

    #[Override]
    final public function createEntity(string $entityFqcn): never
    {
        throw new BadMethodCallException(
            Str\format(
                'Method "%s" should not be called. Call createDto() if needed in DTO controllers. ',
                __METHOD__,
            ),
        );
    }

    /**
     * @return TDto
     */
    public function createDto(): object
    {
        $className = static::getDtoFqcn();

        return new $className();
    }

    /**
     * @param TEntity $entity
     *
     * @return TDto
     */
    abstract public function createDtoFromEntity(object $entity): object;

    /**
     * @param TEntity $entity
     * @param TDto    $dto
     */
    abstract public function updateEntityFromDto(object $entity, object $dto): void;

    /**
     * This method should be identical to the parent edit() method except that:
     *  - it uses updateEntityFromDto($originalEntity, $dto) to update the entity from the DTO.
     */
    #[Override]
    public function edit(AdminContext $context): KeyValueStore|Response
    {
        // phpcs:disable
        $entityDto = $context->getEntity();
        $entityInstance = $entityDto->getInstance();

        $dto = $this->createDtoFromEntity($entityInstance);
        $entityDto->setInstance(null);
        $entityDto->setInstance($dto);

        $event = new BeforeCrudActionEvent($context);
        $this->container->get('event_dispatcher')->dispatch($event);
        if ($event->isPropagationStopped()) {
            return $event->getResponse();
        }

        if (!$this->isGranted(Permission::EA_EXECUTE_ACTION, ['action' => Action::EDIT, 'entity' => $context->getEntity(), 'entityFqcn' => $context->getEntity()->getFqcn()])) {
            throw new ForbiddenActionException($context);
        }

        if (!$context->getEntity()->isAccessible()) {
            throw new InsufficientEntityPermissionException($context);
        }

        $this->container->get(FieldFactory::class)->processFields($context->getEntity(), FieldCollection::new($this->configureFields(Crud::PAGE_EDIT)), Crud::PAGE_EDIT);
        $context->getCrud()->setFieldAssets($this->getFieldAssets($context->getEntity()->getFields()));
        $this->container->get(ActionFactory::class)->processEntityActions($context->getEntity(), $context->getCrud()->getActionsConfig());
        /** @var TDto $dto */
        $dto = $context->getEntity()->getInstance();

        if ($context->getRequest()->isXmlHttpRequest()) {
            if ('PATCH' !== $context->getRequest()->getMethod()) {
                throw new MethodNotAllowedHttpException(['PATCH']);
            }

            if (!$this->isCsrfTokenValid(BooleanField::CSRF_TOKEN_NAME, $context->getRequest()->query->get('csrfToken'))) {
                if (class_exists(InvalidCsrfTokenException::class)) {
                    throw new InvalidCsrfTokenException();
                } else {
                    return new Response('Invalid CSRF token.', 400);
                }
            }

            $fieldName = $context->getRequest()->query->get('fieldName');
            $newValue = 'true' === mb_strtolower($context->getRequest()->query->get('newValue'));

            try {
                $event = $this->ajaxEdit($context->getEntity(), $fieldName, $newValue);
            } catch (\Exception $e) {
                throw new BadRequestHttpException($e->getMessage());
            }

            if ($event->isPropagationStopped()) {
                return $event->getResponse();
            }

            return new Response($newValue ? '1' : '0');
        }

        $editForm = $this->createEditForm($context->getEntity(), $context->getCrud()->getEditFormOptions(), $context);
        $editForm->handleRequest($context->getRequest());
        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->processUploadedFiles($editForm);

            $this->updateEntityFromDto($entityInstance, $dto);
            $entityDto->setInstance(null);
            $entityDto->setInstance($entityInstance);

            $event = new BeforeEntityUpdatedEvent($entityInstance);
            $this->container->get('event_dispatcher')->dispatch($event);
            $entityInstance = $event->getEntityInstance();

            $this->updateEntity($this->container->get('doctrine')->getManagerForClass($context->getEntity()->getFqcn()), $entityInstance);

            $this->container->get('event_dispatcher')->dispatch(new AfterEntityUpdatedEvent($entityInstance));

            return $this->getRedirectResponseAfterSave($context, Action::EDIT);
        }

        $responseParameters = $this->configureResponseParameters(KeyValueStore::new([
            'pageName' => Crud::PAGE_EDIT,
            'templateName' => 'crud/edit',
            'edit_form' => $editForm,
            'entity' => $context->getEntity(),
        ]));

        $event = new AfterCrudActionEvent($context, $responseParameters);
        $this->container->get('event_dispatcher')->dispatch($event);
        if ($event->isPropagationStopped()) {
            return $event->getResponse();
        }

        return $responseParameters;
        // phpcs:enable
    }

    /**
     * This method should be identical to the parent new() method except that:
     *  - it uses the createDto() method to create a new instance of the DTO instead of the entity.
     *  - it uses createEntityFromDto($dto) to convert the DTO to the entity before persisting it.
     *
     * In some scenarios, the entityId query param will get left in the generated urls when :
     *  - you click around the admin
     *  - (by choice) setting some ID in the URL to be used in the `new` action (creating related entities)
     * When creating a new entity, it causes the admin context provider to try and load the entity by ID and set it on the EntityDto.
     * This then causes an exception when we later try to setInstance on the EntityDto to a Dto class and not the Entity.
     */
    #[Override]
    public function new(AdminContext $context): KeyValueStore|Response
    {
        // phpcs:disable
        $event = new BeforeCrudActionEvent($context);
        $this->container->get('event_dispatcher')->dispatch($event);
        if ($event->isPropagationStopped()) {
            return $event->getResponse();
        }

        if (!$this->isGranted(Permission::EA_EXECUTE_ACTION, ['action' => Action::NEW, 'entity' => null, 'entityFqcn' => $context->getEntity()->getFqcn()])) {
            throw new ForbiddenActionException($context);
        }

        if (!$context->getEntity()->isAccessible()) {
            throw new InsufficientEntityPermissionException($context);
        }

        $context->getEntity()->setInstance(null);
        $context->getEntity()->setInstance($this->createDto());
        $this->container->get(FieldFactory::class)->processFields($context->getEntity(), FieldCollection::new($this->configureFields(Crud::PAGE_NEW)), Crud::PAGE_NEW);
        $context->getCrud()->setFieldAssets($this->getFieldAssets($context->getEntity()->getFields()));
        $this->container->get(ActionFactory::class)->processEntityActions($context->getEntity(), $context->getCrud()->getActionsConfig());

        $newForm = $this->createNewForm($context->getEntity(), $context->getCrud()->getNewFormOptions(), $context);
        $newForm->handleRequest($context->getRequest());

        /** @var TDto $entityInstance */
        $entityInstance = $newForm->getData();
        $context->getEntity()->setInstance(null);
        $context->getEntity()->setInstance($entityInstance);

        if ($newForm->isSubmitted() && $newForm->isValid()) {
            $this->processUploadedFiles($newForm);

            $entityInstance = $this->createEntityFromDto($entityInstance);
            $event = new BeforeEntityPersistedEvent($entityInstance);
            $this->container->get('event_dispatcher')->dispatch($event);
            $entityInstance = $event->getEntityInstance();

            $this->persistEntity($this->container->get('doctrine')->getManagerForClass($context->getEntity()->getFqcn()), $entityInstance);

            $this->container->get('event_dispatcher')->dispatch(new AfterEntityPersistedEvent($entityInstance));
            $context->getEntity()->setInstance($entityInstance);

            return $this->getRedirectResponseAfterSave($context, Action::NEW);
        }

        $responseParameters = $this->configureResponseParameters(KeyValueStore::new([
            'pageName' => Crud::PAGE_NEW,
            'templateName' => 'crud/new',
            'entity' => $context->getEntity(),
            'new_form' => $newForm,
        ]));

        $event = new AfterCrudActionEvent($context, $responseParameters);
        $this->container->get('event_dispatcher')->dispatch($event);
        if ($event->isPropagationStopped()) {
            return $event->getResponse();
        }

        return $responseParameters;
        // phpcs:enable
    }
}
