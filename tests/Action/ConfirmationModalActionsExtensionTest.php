<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Action;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\ActionGroup;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Protung\EasyAdminPlusBundle\Action\ConfirmationModal;
use Protung\EasyAdminPlusBundle\Action\ConfirmationModalActionsExtension;
use Symfony\Component\Translation\TranslatableMessage;

use function version_compare;

#[CoversClass(ConfirmationModalActionsExtension::class)]
final class ConfirmationModalActionsExtensionTest extends TestCase
{
    public function testSupportsOnlyEasyAdmin57AndLater(): void
    {
        self::assertSame(
            version_compare(EasyAdminBundle::VERSION, '5.7.0', '>='),
            (new ConfirmationModalActionsExtension())->supports(AdminContext::forTesting()),
        );
    }

    public function testExtendSendsAConfirmationModalAsTitleAndContent(): void
    {
        $title          = new TranslatableMessage('action.title');
        $content        = new TranslatableMessage('action.content');
        $groupedTitle   = new TranslatableMessage('grouped_action.title');
        $groupedContent = new TranslatableMessage('grouped_action.content');

        $action         = Action::new('action')->linkToCrudAction('action')->askConfirmation(new ConfirmationModal($title, $content), 'Confirm');
        $actionForGroup = Action::new('grouped_action')->linkToCrudAction('groupedAction')->askConfirmation(new ConfirmationModal($groupedTitle, $groupedContent));

        $actions = Actions::new()
            ->add(Crud::PAGE_DETAIL, $action)
            ->add(Crud::PAGE_INDEX, ActionGroup::new('group')->addAction($actionForGroup));

        (new ConfirmationModalActionsExtension())->extend($actions, AdminContext::forTesting());

        self::assertTrue($action->getAsDto()->getConfirmationMessage());
        self::assertSame($title, $action->getAsDto()->getHtmlAttributes()['data-action-confirmation-title']);
        self::assertSame($content, $action->getAsDto()->getHtmlAttributes()['data-action-confirmation-content']);
        self::assertSame('Confirm', $action->getAsDto()->getConfirmationButtonLabel());
        self::assertTrue($actionForGroup->getAsDto()->getConfirmationMessage());
        self::assertSame($groupedTitle, $actionForGroup->getAsDto()->getHtmlAttributes()['data-action-confirmation-title']);
        self::assertSame($groupedContent, $actionForGroup->getAsDto()->getHtmlAttributes()['data-action-confirmation-content']);
    }

    public function testExtendLeavesOtherConfirmationsAlone(): void
    {
        $modal = new ConfirmationModal(new TranslatableMessage('title'), new TranslatableMessage('content'));

        $withoutConfirmation  = Action::new('without_confirmation')->linkToCrudAction('withoutConfirmation');
        $withDefaultMessage   = Action::new('with_default_message')->linkToCrudAction('withDefaultMessage')->askConfirmation();
        $withPlainMessage     = Action::new('with_plain_message')->linkToCrudAction('withPlainMessage')->askConfirmation('Are you sure?');
        $deleteWithModal      = Action::new(Action::DELETE)->linkToCrudAction(Action::DELETE)->askConfirmation($modal);
        $batchActionWithModal = Action::new('batch_action')->linkToCrudAction('batchAction')->createAsBatchAction()->askConfirmation($modal);

        $actions = Actions::new()
            ->add(Crud::PAGE_DETAIL, $withoutConfirmation)
            ->add(Crud::PAGE_DETAIL, $withDefaultMessage)
            ->add(Crud::PAGE_DETAIL, $withPlainMessage)
            ->add(Crud::PAGE_DETAIL, $deleteWithModal)
            ->add(Crud::PAGE_INDEX, $batchActionWithModal);

        (new ConfirmationModalActionsExtension())->extend($actions, AdminContext::forTesting());

        self::assertFalse($withoutConfirmation->getAsDto()->getConfirmationMessage());
        self::assertTrue($withDefaultMessage->getAsDto()->getConfirmationMessage());
        self::assertSame('Are you sure?', $withPlainMessage->getAsDto()->getConfirmationMessage());
        self::assertSame($modal, $deleteWithModal->getAsDto()->getConfirmationMessage());
        self::assertSame($modal, $batchActionWithModal->getAsDto()->getConfirmationMessage());

        foreach ([$withoutConfirmation, $withDefaultMessage, $withPlainMessage, $deleteWithModal, $batchActionWithModal] as $action) {
            self::assertArrayNotHasKey('data-action-confirmation-title', $action->getAsDto()->getHtmlAttributes());
        }
    }
}
