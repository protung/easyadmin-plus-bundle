<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Twig\Component\Option\AlertVariant;
use Override;
use Psl\Dict;
use Psl\Type;
use Stringable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Translated flash messages for the base controllers.
 *
 * @phpstan-require-extends AbstractController
 */
trait FlashMessages
{
    protected function addFlashMessageSuccess(string|Stringable|TranslatableInterface $message): void
    {
        $this->addFlashMessage(AlertVariant::Success, $message);
    }

    protected function addFlashMessageWarning(string|Stringable|TranslatableInterface $message): void
    {
        $this->addFlashMessage(AlertVariant::Warning, $message);
    }

    protected function addFlashMessageError(string|Stringable|TranslatableInterface $message): void
    {
        $this->addFlashMessage(AlertVariant::Error, $message);
    }

    protected function addFlashMessage(AlertVariant $type, string|Stringable|TranslatableInterface $message): void
    {
        // We check against TranslatableInterface because the implementation might be Stringable as well.
        if (! $message instanceof TranslatableInterface) {
            $message = new TranslatableMessage((string) $message, [], $this->flashMessageTranslationDomain());
        }

        $this->addFlash($type->value, $message->trans($this->translator()));
    }

    /**
     * The translation domain of flash messages given as strings, null for the default domain.
     */
    protected function flashMessageTranslationDomain(): string|null
    {
        return null;
    }

    protected function translator(): TranslatorInterface
    {
        return Type\instance_of(TranslatorInterface::class)->coerce($this->container->get(TranslatorInterface::class));
    }

    /**
     * @return array<array-key, string|SubscribedService>
     */
    #[Override]
    public static function getSubscribedServices(): array
    {
        return Dict\merge(
            parent::getSubscribedServices(),
            [
                TranslatorInterface::class => '?' . TranslatorInterface::class,
            ],
        );
    }
}
