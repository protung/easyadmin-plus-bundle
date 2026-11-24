<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Controller;

use BadMethodCallException;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionConfigDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
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
use Generator;
use Override;
use Protung\EasyAdminPlusBundle\Dto\EntityDtoInstanceSetter;
use Psl\Str;
use Psl\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;

use function method_exists;
use function trigger_deprecation;
use function version_compare;

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
     * @return TEntity|Generator<TEntity>
     */
    abstract public function createEntityFromDto(object $dto): object;

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
     *  - it handles the form request like EasyAdmin 5.10 does, on every EasyAdmin version (see handleFormRequest()).
     *  - it handles boolean switches like EasyAdmin 5.10.3 does, on every EasyAdmin version, except that it only deprecates
     *    the CSRF token ID shared by all switches and only checks the save actions (see isAnySaveActionGranted()) on the
     *    versions where EasyAdmin does.
     *
     * A boolean switch on a list saves nothing unless the controller overrides ajaxEdit(): EasyAdmin's refuses the DTO,
     * as it's not an instance of the entity. Use renderAsSwitch(false) to show the value without a switch.
     */
    #[Override]
    public function edit(AdminContext $context): KeyValueStore|Response
    {
        // phpcs:disable
        $entityDto = $context->getEntity();
        $entityInstance = $entityDto->getInstance();

        $dto = $this->createDtoFromEntity($entityInstance);
        EntityDtoInstanceSetter::setInstance($entityDto, $dto);

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

        $this->container->get(FieldFactory::class)->processFields($context->getEntity(), new FieldCollection($this->configureFields(Crud::PAGE_EDIT)), Crud::PAGE_EDIT);
        $context->getCrud()->setFieldAssets($this->getFieldAssets($context->getEntity()->getFields()));
        $this->container->get(ActionFactory::class)->processEntityActions($context->getEntity(), $context->getCrud()->getActionsConfig());
        /** @var TDto $dto */
        $dto = $context->getEntity()->getInstance();

        if ($context->getRequest()->isXmlHttpRequest()) {
            if ('PATCH' !== $context->getRequest()->getMethod()) {
                throw new MethodNotAllowedHttpException(['PATCH']);
            }

            // the switch saves the entity, so it's only allowed to users who can save the edit form
            if (!$this->isAnySaveActionGranted($context)) {
                throw new ForbiddenActionException($context);
            }

            $fieldName = $context->getRequest()->query->get('fieldName');
            $csrfToken = $context->getRequest()->query->get('csrfToken');

            // the token ID includes the CRUD controller and the property, so the token
            // of a switch is not valid for other fields or other CRUD controllers
            if (!$this->isCsrfTokenValid(BooleanField::CSRF_TOKEN_NAME.'-'.$context->getCrud()->getControllerFqcn().'-'.$fieldName, $csrfToken)) {
                if (!$this->isCsrfTokenValid(BooleanField::CSRF_TOKEN_NAME, $csrfToken)) {
                    throw new InvalidCsrfTokenException();
                }

                if (version_compare(EasyAdminBundle::VERSION, '5.10.1', '>=')) {
                    @trigger_deprecation('easycorp/easyadmin-bundle', '5.10.1', 'Using the "%s" CSRF token ID in boolean switches is deprecated and it will not be valid in EasyAdmin 6.0. Use "%s-<crud-controller-fqcn>-<property-name>" instead.', BooleanField::CSRF_TOKEN_NAME, BooleanField::CSRF_TOKEN_NAME);
                }
            }

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
        $this->handleFormRequest($editForm, $context->getRequest());
        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->processUploadedFiles($editForm);

            $this->updateEntityFromDto($entityInstance, $dto);

            $event = new BeforeEntityUpdatedEvent($entityInstance);
            $this->container->get('event_dispatcher')->dispatch($event);
            $entityInstance = $event->getEntityInstance();

            $this->updateEntity($this->container->get('doctrine')->getManagerForClass($context->getEntity()->getFqcn()), $entityInstance);

            $event = new AfterEntityUpdatedEvent($entityInstance);
            $this->container->get('event_dispatcher')->dispatch($event);
            EntityDtoInstanceSetter::setInstance($entityDto, $entityInstance);
            if ($event->isPropagationStopped()) {
                return $event->getResponse();
            }

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
     *  - it handles the form request like EasyAdmin 5.10 does, on every EasyAdmin version (see handleFormRequest()).
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

        EntityDtoInstanceSetter::setInstance($context->getEntity(), $this->createDto());
        $this->container->get(FieldFactory::class)->processFields($context->getEntity(), new FieldCollection($this->configureFields(Crud::PAGE_NEW)), Crud::PAGE_NEW);
        $context->getCrud()->setFieldAssets($this->getFieldAssets($context->getEntity()->getFields()));
        $this->container->get(ActionFactory::class)->processEntityActions($context->getEntity(), $context->getCrud()->getActionsConfig());

        $newForm = $this->createNewForm($context->getEntity(), $context->getCrud()->getNewFormOptions(), $context);
        $this->handleFormRequest($newForm, $context->getRequest());

        /** @var TDto $entityInstance */
        $entityInstance = $newForm->getData();
        EntityDtoInstanceSetter::setInstance($context->getEntity(), $entityInstance);

        if ($newForm->isSubmitted() && $newForm->isValid()) {
            $this->processUploadedFiles($newForm);

            $entityInstance = $this->createEntityFromDto($entityInstance);
            $event = new BeforeEntityPersistedEvent($entityInstance);
            $this->container->get('event_dispatcher')->dispatch($event);
            $entityInstance = $event->getEntityInstance();

            $this->persistEntity($this->container->get('doctrine')->getManagerForClass($context->getEntity()->getFqcn()), $entityInstance);

            $event = new AfterEntityPersistedEvent($entityInstance);
            $this->container->get('event_dispatcher')->dispatch($event);
            EntityDtoInstanceSetter::setInstance($context->getEntity(), $entityInstance);
            if ($event->isPropagationStopped()) {
                return $event->getResponse();
            }

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

    #[Override]
    public function createNewFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        return parent::createNewFormBuilder($entityDto, $formOptions, $context)
            ->addEventListener(
                FormEvents::POST_SUBMIT,
                function (FormEvent $event): void {
                    $this->onPostSubmit(
                        Type\instance_of(static::getDtoFqcn())->coerce($event->getData()),
                    );
                },
                priority: 100, // Must stay above Symfony's ValidationListener, which subscribes to POST_SUBMIT at the default priority of 0.
            );
    }

    #[Override]
    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        return parent::createEditFormBuilder($entityDto, $formOptions, $context)
            ->addEventListener(
                FormEvents::POST_SUBMIT,
                function (FormEvent $event): void {
                    $this->onPostSubmit(
                        Type\instance_of(static::getDtoFqcn())->coerce($event->getData()),
                    );
                },
                priority: 100, // Must stay above Symfony's ValidationListener, which subscribes to POST_SUBMIT at the default priority of 0.
            );
    }

    /**
     * @param TEntity|Generator<TEntity> $entityInstance
     */
    #[Override]
    final public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($this->isGenerator($entityInstance)) {
            foreach ($entityInstance as $item) {
                parent::persistEntity($entityManager, $item);
            }
        } else {
            parent::persistEntity($entityManager, $entityInstance);
        }
    }

    /**
     * @param TEntity|Generator<TEntity> $entityInstance
     */
    #[Override]
    final public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($this->isGenerator($entityInstance)) {
            foreach ($entityInstance as $item) {
                parent::updateEntity($entityManager, $item);
            }
        } else {
            parent::updateEntity($entityManager, $entityInstance);
        }
    }

    /** @param TDto $dto */
    protected function onPostSubmit(object $dto): void
    {
    }

    /**
     * TEntity is only bound to object, so the analysers can't rule out that an entity is itself a Generator.
     *
     * @param TEntity|Generator<TEntity> $entityInstance
     *
     * @phpstan-assert-if-true Generator<TEntity> $entityInstance
     * @phpstan-assert-if-false TEntity $entityInstance
     */
    private function isGenerator(object $entityInstance): bool
    {
        return $entityInstance instanceof Generator;
    }

    // phpcs:disable
    /**
     * A byte-identical copy of the private AbstractCrudController::handleFormRequest() of EasyAdmin 5.10, so copy it
     * again when EasyAdmin changes it. It is used on every EasyAdmin version: the buttons that save a form have the
     * same name in all of them. phpstan.neon ignores the errors of its EasyAdmin code.
     */
    private function handleFormRequest(FormInterface $form, Request $request): void
    {
        $form->handleRequest($request);

        // browsers don't send disabled fields nor unchecked checkboxes, so a form without a CSRF token
        // and only those fields posts nothing under its name and Symfony doesn't consider it submitted;
        // the button that saves the form is outside of it, so use it to detect the submission (see #7819)
        if ($form->isSubmitted() || $request->getMethod() !== $form->getConfig()->getMethod()) {
            return;
        }

        if (null === ($request->request->all()['ea']['newForm']['btn'] ?? null)) {
            return;
        }

        $form->submit([], 'PATCH' !== $request->getMethod());
    }

    /**
     * A copy of the private AbstractCrudController::isAnySaveActionGranted() of EasyAdmin 5.10.3, so copy it again when
     * EasyAdmin changes it, except that it checks nothing on the EasyAdmin versions that don't either, which have no
     * ActionConfigDto::getSaveActionNames(). phpstan.neon and mago.toml ignore the errors of its EasyAdmin code.
     *
     * Save actions don't have their own URL (the entity is saved by the action that renders their buttons),
     * so the permissions and the disabled state of the ones added to the edit page are checked here.
     * When the page has none of them (e.g. they were replaced by custom actions) nothing is checked.
     *
     * @param AdminContext<TEntity> $context
     */
    private function isAnySaveActionGranted(AdminContext $context): bool
    {
        if (!method_exists(ActionConfigDto::class, 'getSaveActionNames')) {
            return true;
        }

        $saveActionNames = $context->getCrud()->getActionsConfig()->getSaveActionNames(Crud::PAGE_EDIT);
        foreach ($saveActionNames as $actionName) {
            if ($this->isGranted(Permission::EA_EXECUTE_ACTION, ['action' => $actionName, 'entity' => $context->getEntity(), 'entityFqcn' => $context->getEntity()->getFqcn()])) {
                return true;
            }
        }

        return [] === $saveActionNames;
    }
    // phpcs:enable
}
