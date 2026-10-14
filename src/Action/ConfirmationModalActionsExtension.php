<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Action;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Action\ActionsExtensionInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionGroupDto;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Generator;
use Override;
use Psl\Type;

use function version_compare;

/**
 * Since 5.7, EasyAdmin shows a custom confirmation message as body text and hides the modal's title.
 * A {@see ConfirmationModal} is sent as the title and description the delete confirmation has instead.
 */
final readonly class ConfirmationModalActionsExtension implements ActionsExtensionInterface
{
    /**
     * Before 5.7, EasyAdmin's JavaScript ignores the title attribute and would show its generic title instead,
     * so the {@see ConfirmationModal} is left to be shown as a single message.
     */
    #[Override]
    public function supports(AdminContext $context): bool
    {
        return version_compare(EasyAdminBundle::VERSION, '5.7.0', '>=');
    }

    #[Override]
    public function extend(Actions $actions, AdminContext $context): void
    {
        foreach ($this->actionDtos($actions) as $actionDto) {
            $confirmationModal = $actionDto->getConfirmationMessage();

            // A delete asking with `true` gets its title from EasyAdmin, which would overwrite this one; batch actions use their own modal.
            if (! $confirmationModal instanceof ConfirmationModal || $actionDto->getName() === Action::DELETE || $actionDto->isBatchAction()) {
                continue;
            }

            $actionDto->setConfirmationMessage(true);
            $actionDto->setHtmlAttribute('data-action-confirmation-title', $confirmationModal->title);
            $actionDto->setHtmlAttribute('data-action-confirmation-content', $confirmationModal->content);
        }
    }

    /** @return Generator<ActionDto> */
    private function actionDtos(Actions $actions): Generator
    {
        $actionsByPage = $actions->getAsDto(null)->getActions();

        // Passing `null` asks for every page's actions, which is the array keyed by page name instead of a collection.
        Type\mixed_dict()->assert($actionsByPage);

        foreach ($actionsByPage as $pageActions) {
            foreach ($pageActions as $action) {
                if (! $action instanceof ActionGroupDto) {
                    yield $action;

                    continue;
                }

                $mainAction = $action->getMainAction();
                if ($mainAction !== null) {
                    yield $mainAction;
                }

                yield from $action->getActions();
            }
        }
    }
}
