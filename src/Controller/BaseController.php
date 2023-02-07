<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Controller;

use Psl\Dict;
use Stringable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class BaseController extends AbstractController
{
    protected function addFlashMessageSuccess(string|Stringable|TranslatableInterface $message): void
    {
        $this->addFlashMessage(Flash::Success, $message);
    }

    protected function addFlashMessageWarning(string|Stringable|TranslatableInterface $message): void
    {
        $this->addFlashMessage(Flash::Warning, $message);
    }

    protected function addFlashMessageError(string|Stringable|TranslatableInterface $message): void
    {
        $this->addFlashMessage(Flash::Error, $message);
    }

    protected function addFlashMessage(Flash $type, string|Stringable|TranslatableInterface $message): void
    {
        // We check against TranslatableInterface because the implementation might be Stringable as well.
        if (! $message instanceof TranslatableInterface) {
            $message = new TranslatableMessage((string) $message);
        }

        $this->addFlash(
            $type->value,
            $message->trans($this->container->get(TranslatorInterface::class)),
        );
    }

    /**
     * @return array<array-key, string|SubscribedService>
     */
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
