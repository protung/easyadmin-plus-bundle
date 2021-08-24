<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psl\Dict;
use Psl\Iter;
use Psl\Vec;
use RuntimeException;
use Stringable;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

abstract class BaseCrudController extends AbstractCrudController
{
    protected const FIELD_SORT_ASC  = 'ASC';
    protected const FIELD_SORT_DESC = 'DESC';

    private const FLASH_SUCCESS = 'success';
    private const FLASH_WARNING = 'warning';
    private const FLASH_ERROR   = 'danger';

    /**
     * Calling this method will only disable the standard actions.
     */
    protected function allowOnlyActions(Actions $actions, string ...$allowedActions): Actions
    {
        $allActions = [
            Action::BATCH_DELETE,
            Action::DELETE,
            Action::DETAIL,
            Action::EDIT,
            Action::INDEX,
            Action::NEW,
        ];

        return $actions->disable(
            ...Vec\values(Dict\diff($allActions, $allowedActions))
        );
    }

    /**
     * Calling this method will only disable the standard actions.
     */
    protected function allowOnlyIndexAction(Actions $actions): Actions
    {
        return $this->allowOnlyActions($actions, Action::INDEX);
    }

    /**
     * Calling this method will only disable the standard actions.
     */
    protected function allowOnlyDetailAction(Actions $actions): Actions
    {
        return $this->allowOnlyActions($actions, Action::DETAIL);
    }

    protected function setActionsPermissions(Actions $actions, string $permission): Actions
    {
        $pages = [Crud::PAGE_INDEX, Crud::PAGE_NEW, Crud::PAGE_DETAIL, Crud::PAGE_EDIT];
        foreach ($pages as $page) {
            Iter\apply(
                $actions->getAsDto($page)->getActions(),
                static function (ActionDto $actionDto) use ($actions, $permission): void {
                    $actions->setPermission($actionDto->getName(), $permission);
                }
            );
        }

        return $actions;
    }

    protected function addConfirmationForAction(
        Action $action,
        string|Stringable|TranslatableInterface $title,
        string|Stringable|TranslatableInterface $description
    ): Action {
         $action
            ->getAsDto()
            ->addHtmlAttributes(
                [
                    'data-protung-easyadmin-plus-extension-modal-confirm-trigger' => '1',
                    'data-protung-easyadmin-plus-extension-modal-confirm-title' => $this->translate($title),
                    'data-protung-easyadmin-plus-extension-modal-confirm-description' => $this->translate($description),
                ]
            );

         return $action;
    }

    public function addCancelAction(
        Actions $actions,
        string $page,
        string $targetAction,
        string $actionName = 'cancel',
        string $label = 'Cancel',
        ?callable $callback = null
    ): void {
        Assert::oneOf($targetAction, [Action::INDEX, Action::DETAIL]);

        $actions->add(
            $page,
            Action::new($actionName, $label)
                ->linkToUrl($callback ?? fn () => $this->adminUrlGenerator()->setAction($targetAction)->generateUrl())
                ->setCssClass('btn btn-secondary')
        );
    }

    protected function renderInDropdown(Action $action, bool $shouldRenderInDropdown): Action
    {
        $action
            ->getAsDto()
            ->addHtmlAttributes(
                ['data-protung-easyadmin-plus-extension-action-render-in-dropdown' => $shouldRenderInDropdown ? '1' : '-1']
            );

        return $action;
    }

    protected function currentAdminContext(): AdminContext
    {
        $currentAdminContext = $this->get(AdminContextProvider::class)->getContext();
        if ($currentAdminContext === null) {
            throw new RuntimeException('Current request is not in an EasyAdmin context.');
        }

        return $currentAdminContext;
    }

    protected function addFlashMessageSuccess(string|Stringable|TranslatableInterface $message): void
    {
        $this->addFlashMessage(self::FLASH_SUCCESS, $message);
    }

    protected function addFlashMessageWarning(string|Stringable|TranslatableInterface $message): void
    {
        $this->addFlashMessage(self::FLASH_WARNING, $message);
    }

    protected function addFlashMessageError(string|Stringable|TranslatableInterface $message): void
    {
        $this->addFlashMessage(self::FLASH_ERROR, $message);
    }

    protected function addFlashMessage(string $type, string|Stringable|TranslatableInterface $message): void
    {
        $this->addFlash($type, $this->translate($message));
    }

    private function translate(string|Stringable|TranslatableInterface $message): string
    {
        // We check against TranslatableInterface because the implementation might be Stringable as well.
        if (! $message instanceof TranslatableInterface) {
            $translationDomain = $this->currentAdminContext()->getI18n()->getTranslationDomain();
            $message           = new TranslatableMessage((string) $message, [], $translationDomain);
        }

        return $message->trans($this->translator());
    }

    protected function translator(): TranslatorInterface
    {
        return $this->get(TranslatorInterface::class);
    }

    protected function adminUrlGenerator(): AdminUrlGenerator
    {
        return $this->get(AdminUrlGenerator::class);
    }

    /**
     * @return array<array-key, string>
     */
    public static function getSubscribedServices(): array
    {
        return Dict\merge(
            parent::getSubscribedServices(),
            [
                TranslatorInterface::class => '?' . TranslatorInterface::class,
            ]
        );
    }
}
