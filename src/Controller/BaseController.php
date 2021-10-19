<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Controller;

use Psl\Dict;
use Stringable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class BaseController extends AbstractController
{
    private const FLASH_SUCCESS = 'success';

    private const FLASH_WARNING = 'warning';

    private const FLASH_ERROR = 'danger';

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
        // We check against TranslatableInterface because the implementation might be Stringable as well.
        if (! $message instanceof TranslatableInterface) {
            $message = new TranslatableMessage((string) $message);
        }

        $this->addFlash(
            $type,
            $message->trans($this->get(TranslatorInterface::class))
        );
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
