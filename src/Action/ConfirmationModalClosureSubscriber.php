<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Action;

use EasyCorp\Bundle\EasyAdminBundle\Collection\ActionCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\EntityCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionGroupDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterCrudActionEvent;
use Generator;
use Override;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function version_compare;

/**
 * Since 5.10, `askConfirmation()` also takes a closure, which EasyAdmin calls for each entity after the actions extensions have run,
 * so {@see ConfirmationModalActionsExtension} never sees the {@see ConfirmationModal} it returns.
 * Once the CRUD action has built the actions, the modal EasyAdmin made a single message of is sent as title and  content instead, like the extension does.
 */
final readonly class ConfirmationModalClosureSubscriber implements EventSubscriberInterface
{
    /** @return array<class-string, string> */
    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [AfterCrudActionEvent::class => 'sendClosureConfirmationModals'];
    }

    public function sendClosureConfirmationModals(AfterCrudActionEvent $event): void
    {
        // Before 5.10, a ConfirmationModal still found here was passed directly and is left as the single message.
        if (version_compare(EasyAdminBundle::VERSION, '5.10.0', '<')) {
            return;
        }

        // The entities' actions of the index, detail, edit and new pages, and the index page's global actions.
        foreach (['entities', 'entity', 'global_actions'] as $parameterName) {
            foreach ($this->actionDtos($event->getResponseParameters()->get($parameterName)) as $actionDto) {
                $confirmationModal = $actionDto->getConfirmationMessage();

                // The same exclusions as the extension's.
                if (! $confirmationModal instanceof ConfirmationModal || $actionDto->getName() === Action::DELETE || $actionDto->isBatchAction()) {
                    continue;
                }

                $htmlAttributes = $actionDto->getHtmlAttributes();
                // EasyAdmin's JavaScript hides the title whenever there is a message.
                unset($htmlAttributes['data-action-confirmation-message']);
                $htmlAttributes['data-action-confirmation-title']   = $confirmationModal->title;
                $htmlAttributes['data-action-confirmation-content'] = $confirmationModal->content;

                $actionDto->setHtmlAttributes($htmlAttributes);
                $actionDto->setConfirmationMessage(true);
            }
        }
    }

    /**
     * The actions of an entity collection, an entity, an action collection or an action group, and an action itself.
     * Anything else a response parameter can hold has none.
     *
     * @return Generator<ActionDto>
     */
    private function actionDtos(mixed $value): Generator
    {
        if ($value instanceof EntityCollection) {
            foreach ($value as $entityDto) {
                yield from $this->actionDtos($entityDto);
            }

            return;
        }

        if ($value instanceof EntityDto) {
            yield from $this->actionDtos($value->getActions());

            return;
        }

        if ($value instanceof ActionCollection) {
            foreach ($value as $action) {
                yield from $this->actionDtos($action);
            }

            return;
        }

        if ($value instanceof ActionGroupDto) {
            yield from $this->actionDtos($value->getMainAction());
            yield from $value->getActions();

            return;
        }

        if ($value instanceof ActionDto) {
            yield $value;
        }
    }
}
